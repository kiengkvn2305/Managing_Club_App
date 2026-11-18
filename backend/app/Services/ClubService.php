<?php

namespace App\Services;

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMembership;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClubService
{
    public function __construct(private AuditService $audit) {}

    public function create(User $user, array $data): Club
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['formats'])) $data['format'] = $data['formats'][0];
            $club = Club::create($data + ['slug' => (Str::slug($data['name']) ?: 'club').'-'.Str::lower(Str::random(12))]);
            $club->memberships()->create(['user_id' => $user->id, 'role' => ClubRole::Owner, 'status' => 'active']);
            $this->audit->record($club, $user, 'club.created', $club);

            return $club;
        });
    }

    public function savePlayer(Club $club, User $actor, array $data, ?int $id = null): PlayerProfile
    {
        return DB::transaction(function () use ($club, $actor, $data, $id) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $player = $id ? $club->players()->whereKey($id)->lockForUpdate()->firstOrFail() : $club->players()->make(['status' => 'active']);
            $player->fill($data);
            if ($id && $player->status !== 'inactive') {
                $assignments = DB::table('team_players')->where('club_id', $club->id)->where('player_id', $id)->whereNotNull('jersey_number')->get();
                foreach ($assignments as $assignment) {
                    $conflict = DB::table('team_players')->join('player_profiles', 'player_profiles.id', '=', 'team_players.player_id')->where('team_players.club_id', $club->id)->where('team_players.team_id', $assignment->team_id)->where('team_players.jersey_number', $assignment->jersey_number)->where('team_players.player_id', '!=', $id)->where('player_profiles.status', '!=', 'inactive')->exists();
                    if ($conflict) throw ValidationException::withMessages(['status' => ['Số áo trong một đội đã được sử dụng; hãy đổi số áo trước khi kích hoạt.']]);
                }
            }
            $player->current_jersey = array_key_exists('team_id', $data) && $player->team_id && $player->status !== 'inactive' ? $player->jersey_number : null;
            if ($player->current_jersey !== null && $club->players()->where('team_id', $player->team_id)->where('current_jersey', $player->current_jersey)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['jersey_number' => ['Số áo đã được sử dụng trong đội này.']]);
            }
            $player->save();
            if (array_key_exists('team_id', $data)) {
                $player->teams()->sync($player->team_id ? [$player->team_id => ['club_id' => $club->id, 'jersey_number' => $player->jersey_number, 'position' => $player->position]] : []);
                DB::table('teams')->where('club_id', $club->id)->where('captain_id', $player->id)->when($player->team_id, fn ($q) => $q->where('id', '!=', $player->team_id))->update(['captain_id' => null]);
            }
            $this->audit->record($club, $actor, $id ? 'player.updated' : 'player.created', $player);

            return $player->load(['team', 'teams']);
        });
    }

    public function invite(Club $club, User $actor, array $data): array
    {
        return DB::transaction(function () use ($club, $actor, $data) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $existing = ClubInvitation::where('club_id', $club->id)->where('email', $data['email'])->first();
            if ($existing && ! $existing->accepted_at && $existing->expires_at->isFuture()) {
                throw ValidationException::withMessages(['email' => ['Đã có lời mời còn hiệu lực cho email này.']]);
            }
            if (ClubMembership::where('club_id', $club->id)->whereIn('user_id', User::where('email', $data['email'])->select('id'))->exists()) {
                throw ValidationException::withMessages(['email' => ['Tài khoản đã có quyền truy cập CLB.']]);
            }
            $token = Str::random(64);
            $invitation = ClubInvitation::updateOrCreate(['club_id' => $club->id, 'email' => $data['email']], ['invited_by' => $actor->id, 'role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null]);
            $this->audit->record($club, $actor, 'invitation.created', $invitation);

            return ['id' => $invitation->id, 'email' => $invitation->email, 'role' => $invitation->role, 'token' => $token, 'expires_at' => $invitation->expires_at->toISOString()];
        });
    }

    public function accept(User $user, string $token): Club
    {
        return DB::transaction(function () use ($user, $token) {
            $candidate = ClubInvitation::where('token_hash', hash('sha256', $token))->first();
            if (! $candidate) {
                throw ValidationException::withMessages(['token' => ['Lời mời không hợp lệ hoặc đã hết hạn.']]);
            }
            // Consistent club -> invitation lock order also protects against reissue races.
            $club = Club::whereKey($candidate->club_id)->lockForUpdate()->firstOrFail();
            $invitation = ClubInvitation::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($invitation->token_hash, hash('sha256', $token)) || $invitation->accepted_at || $invitation->expires_at->isPast() || $invitation->email !== mb_strtolower($user->email) || $club->status !== 'active') {
                throw ValidationException::withMessages(['token' => ['Lời mời không hợp lệ, sai email hoặc đã hết hạn.']]);
            }
            if ($club->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['token' => ['Tài khoản đã có quyền truy cập CLB.']]);
            }
            $club->memberships()->create(['user_id' => $user->id, 'role' => $invitation->role, 'status' => 'active']);
            $invitation->update(['accepted_at' => now()]);
            $this->audit->record($club,$user,'invitation.accepted',$invitation);

            return $club;
        });
    }
}
