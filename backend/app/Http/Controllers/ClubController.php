<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClubRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use App\Services\AuditService;
use App\Services\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    public function index(Request $request): mixed
    {
        return ClubResource::collection(Club::whereHas('memberships', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))->orderBy('name')->get());
    }

    public function store(ClubRequest $request, ClubService $service): mixed
    {
        return (new ClubResource($service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(ClubRequest $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club');
        DB::transaction(function () use ($club, $request, $audit) {
            $club = Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            if (isset($data['formats'])) $data['format'] = $data['formats'][0];
            elseif (isset($data['format'])) $data['formats'] = [$data['format']];
            if (isset($data['formats']) && $club->teams()->whereNotIn('format', $data['formats'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['formats' => ['Có đội bóng đang dùng loại sân bị bỏ chọn. Hãy đổi loại sân của đội trước.']]);
            }
            $club->update($data);
            $audit->record($club, $request->user(), 'club.updated', $club);
        });

        return new ClubResource($club->refresh());
    }

    public function destroy(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('delete', $club);
        DB::transaction(function () use ($club, $request, $audit) {
            $locked = Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $locked);
            $audit->record($locked, $request->user(), 'club.deleted', $locked);
            $locked->delete();
        });
        return response()->noContent();
    }

    public function teams(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        $teams = $club->teams()->orderBy('name')->get();
        if ($request->user()->can('viewRoster', $club)) {
            $captains = $club->players()->whereIn('id', $teams->pluck('captain_id')->filter())->pluck('full_name', 'id');
            $managers = DB::table('team_managers')->join('club_members', 'club_members.id', '=', 'team_managers.membership_id')->join('users', 'users.id', '=', 'club_members.user_id')->where('team_managers.club_id', $club->id)->where('club_members.status', 'active')->select('team_managers.team_id', 'club_members.id', 'users.name')->get()->groupBy('team_id');
            $teams->each(function ($team) use ($captains, $managers) { $team->captain_name = $captains[$team->captain_id] ?? null; $team->managers = $managers->get($team->id, collect())->values(); });
        }
        return response()->json(['data' => $teams]);
    }

    public function storeTeam(TeamRequest $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club');
        $team = DB::transaction(function () use ($club, $request, $audit) {
            $club = Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            if (! in_array($request->validated('format'), $club->formats ?: [$club->format], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['format' => ['Loại sân này chưa được CLB chọn.']]);
            }
            $team = $club->teams()->create($request->validated());
            $audit->record($club, $request->user(), 'team.created', $team);

            return $team;
        });

        return response()->json(['data' => $team], 201);
    }

    public function dashboard(Request $request): mixed
    {
        $club = $request->attributes->get('club');

        return response()->json(['data' => ['members_count' => $club->players()->count(), 'active_members_count' => $club->players()->where('status', 'active')->count(), 'teams_count' => $club->teams()->count(), 'managers_count' => $club->memberships()->where('status', 'active')->where('role', '!=', 'member')->count()]]);
    }

    public function renameTeam(Request $request, string $club, int $team, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $target = $tenant->teams()->findOrFail($team);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('teams')->where('club_id', $tenant->id)->ignore($target->id)], 'format' => ['sometimes', 'required', Rule::in(['5', '7', '11'])], 'membership_ids' => ['sometimes', 'array'], 'membership_ids.*' => ['required', 'integer', 'distinct', Rule::exists('club_members', 'id')->where('club_id', $tenant->id)->where('status', 'active')]]);
        DB::transaction(function () use ($target, $data, $tenant, $request, $audit) {
            $lockedClub = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (isset($data['format']) && ! in_array($data['format'], $lockedClub->formats ?: [$lockedClub->format], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['format' => ['Loại sân này chưa được CLB chọn.']]);
            }
            if (isset($data['format']) && $data['format'] !== $target->format) {
                foreach (DB::table('match_lineups')->where('club_id', $tenant->id)->where('team_id', $target->id)->pluck('players') as $players) {
                    if (count(array_filter(json_decode($players, true) ?: [], fn ($p) => ($p['role'] ?? '') === 'starter')) > (int) $data['format']) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['format' => ['Một đội hình đã lưu có quá nhiều cầu thủ đá chính cho loại sân này.']]);
                    }
                }
            }
            $target->update(collect($data)->only(['name', 'format'])->all());
            if (array_key_exists('membership_ids', $data)) {
                DB::table('team_managers')->where('club_id', $tenant->id)->where('team_id', $target->id)->delete();
                foreach ($data['membership_ids'] as $id) DB::table('team_managers')->insert(['club_id' => $tenant->id, 'team_id' => $target->id, 'membership_id' => $id]);
            }
            $audit->record($tenant, $request->user(), 'team.updated', $target);
        });
        return response()->json(['data' => $target]);
    }

    public function destroyTeam(Request $request, string $club, int $team, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        DB::transaction(function () use ($tenant, $team, $request, $audit) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $target = $tenant->teams()->whereKey($team)->lockForUpdate()->firstOrFail();
            $tenant->players()->where('team_id', $team)->update(['team_id' => null, 'current_jersey' => null]);
            DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->delete();
            DB::table('match_lineups')->where('club_id', $tenant->id)->where('team_id', $team)->delete();
            DB::table('team_managers')->where('club_id', $tenant->id)->where('team_id', $team)->delete();
            DB::table('team_trainings')->where('club_id', $tenant->id)->where('team_id', $team)->update(['team_id' => null]);
            $audit->record($tenant, $request->user(), 'team.deleted', $target);
            $target->delete();
        });
        return response()->noContent();
    }

    public function managerCandidates(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewRoster', $club);
        return response()->json(['data' => DB::table('club_members')->join('users', 'users.id', '=', 'club_members.user_id')->where('club_members.club_id', $club->id)->where('club_members.status', 'active')->select('club_members.id', 'users.name')->orderBy('users.name')->get()]);
    }

    public function setTeamPlayers(Request $request, string $club, int $team): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['player_ids' => ['present', 'array'], 'player_ids.*' => ['required', 'integer', 'distinct', Rule::exists('player_profiles', 'id')->where('club_id', $tenant->id)]]);
        DB::transaction(function () use ($tenant, $team, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $target = $tenant->teams()->whereKey($team)->lockForUpdate()->firstOrFail();
            DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->whereNotIn('player_id', $data['player_ids'])->delete();
            $existing = DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->pluck('player_id')->all();
            foreach ($data['player_ids'] as $id) if (! in_array($id, $existing)) DB::table('team_players')->insert(['club_id' => $tenant->id, 'team_id' => $team, 'player_id' => $id]);
            if ($target->captain_id && ! in_array($target->captain_id, $data['player_ids'])) DB::table('teams')->where('club_id', $tenant->id)->where('id', $team)->update(['captain_id' => null]);
            $tenant->players()->where('team_id', $team)->whereNotIn('id', $data['player_ids'])->update(['team_id' => null, 'current_jersey' => null]);
        });
        return response()->noContent();
    }

    public function setTeamJersey(Request $request, string $club, int $team, int $player): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['jersey_number' => ['present', 'nullable', 'integer', 'between:0,999']]);
        DB::transaction(function () use ($tenant, $team, $player, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $membership = DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->where('player_id', $player)->first();
            abort_unless($membership, 404);
            $active = $tenant->players()->whereKey($player)->where('status', '!=', 'inactive')->exists();
            if ($active && $data['jersey_number'] !== null && DB::table('team_players')->join('player_profiles', 'player_profiles.id', '=', 'team_players.player_id')->where('team_players.club_id', $tenant->id)->where('team_players.team_id', $team)->where('team_players.player_id', '!=', $player)->where('team_players.jersey_number', $data['jersey_number'])->where('player_profiles.status', '!=', 'inactive')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['jersey_number' => ['Số áo đã được dùng trong đội này.']]);
            }
            DB::table('team_players')->where('id', $membership->id)->update(['jersey_number' => $data['jersey_number']]);
        });
        return response()->noContent();
    }

    public function removeTeamPlayer(Request $request, string $club, int $team, int $player): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        DB::transaction(function () use ($tenant, $team, $player) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $deleted = DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->where('player_id', $player)->delete();
            abort_unless($deleted, 404);
            DB::table('teams')->where('club_id', $tenant->id)->where('id', $team)->where('captain_id', $player)->update(['captain_id' => null]);
            $tenant->players()->whereKey($player)->where('team_id', $team)->update(['team_id' => null, 'current_jersey' => null]);
        });
        return response()->noContent();
    }

    public function setTeamPosition(Request $request, string $club, int $team, int $player): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['position' => ['present', 'nullable', Rule::in(['GK', 'FIXO', 'ALA', 'PIVOT', 'CB', 'LB', 'RB', 'CDM', 'CM', 'CAM', 'LM', 'RM', 'LW', 'RW', 'ST', 'CF'])]]);
        $changed = DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->where('player_id', $player)->exists();
        abort_unless($changed, 404);
        DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->where('player_id', $player)->update(['position' => $data['position']]);
        return response()->noContent();
    }

    public function setManagers(Request $request, string $club, int $team): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['membership_ids' => ['present', 'array'], 'membership_ids.*' => ['required', 'integer', 'distinct', Rule::exists('club_members', 'id')->where('club_id', $tenant->id)->where('status', 'active')]]);
        DB::transaction(function () use ($tenant, $team, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $tenant->teams()->findOrFail($team);
            DB::table('team_managers')->where('club_id', $tenant->id)->where('team_id', $team)->delete();
            foreach ($data['membership_ids'] as $id) DB::table('team_managers')->insert(['club_id' => $tenant->id, 'team_id' => $team, 'membership_id' => $id]);
        });
        return response()->noContent();
    }
}
