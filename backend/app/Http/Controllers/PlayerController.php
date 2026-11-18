<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Services\AuditService;
use App\Services\ClubService;
use App\Services\TeamDuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlayerController extends Controller
{
    public function mine(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        $player = $club->players()->with(['team', 'teams', 'user'])->where('user_id', $request->user()->id)->first();
        $eligible = ! $player && $club->status === 'active' && $request->user()->hasVerifiedEmail() && $club->players()->whereNull('user_id')->where('email', mb_strtolower($request->user()->email))->exists();
        return response()->json(['data' => $player ? (new PlayerResource($player))->resolve($request) : null, 'eligible' => $eligible]);
    }

    public function claimMine(Request $request, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        if (! $request->user()->hasVerifiedEmail()) throw ValidationException::withMessages(['email' => ['Hãy xác minh email trước khi liên kết hồ sơ.']]);
        DB::transaction(function () use ($tenant, $request, $audit) {
            $club = \App\Models\Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); abort_unless($club->status === 'active', 403);
            abort_unless($club->memberships()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
            if ($club->players()->where('user_id', $request->user()->id)->exists()) throw ValidationException::withMessages(['email' => ['Tài khoản đã được liên kết với một hồ sơ trong CLB này.']]);
            $player = $club->players()->whereNull('user_id')->where('email', mb_strtolower($request->user()->email))->lockForUpdate()->first();
            if (! $player) throw ValidationException::withMessages(['email' => ['Không tìm thấy hồ sơ chưa liên kết có email đã xác minh của bạn.']]);
            $player->user_id = $request->user()->id; $player->save(); $audit->record($club, $request->user(), 'player.self_linked', $player);
        });
        return response()->noContent();
    }

    public function unlinkMine(Request $request, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        DB::transaction(function () use ($tenant, $request, $audit) {
            $club = \App\Models\Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); abort_unless($club->status === 'active', 403);
            abort_unless($club->memberships()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
            $player = $club->players()->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $player->user_id = null; $player->save(); $audit->record($club, $request->user(), 'player.self_unlinked', $player);
        });
        return response()->noContent();
    }
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewRoster', $club);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'team_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'injured'])], 'per_page' => ['nullable', 'integer', 'between:1,100'], 'page' => ['nullable', 'integer', 'min:1'], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'sort' => ['nullable', Rule::in(['full_name', 'created_at', 'jersey_number', 'date_of_birth', 'position'])]]);
        $query = $club->players()->with(['team', 'teams', 'user']);
        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('notes', 'like', $term));
        }
        if (! empty($filters['team_id'])) {
            $query->whereHas('teams', fn ($q) => $q->where('teams.id', $filters['team_id']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $duesStatus = $request->validate(['dues_status' => ['nullable', Rule::in(['paid', 'debt', 'none'])]])['dues_status'] ?? null;
        if ($duesStatus && ! empty($filters['team_id'])) {
            Gate::authorize('viewFinance', $club);
            $summary = app(TeamDuesService::class)->rosterSummary($club->id, (int) $filters['team_id']);
            $ids = array_keys(array_filter($summary, fn ($row) => $duesStatus === 'debt' ? $row['debt'] > 0 : ($duesStatus === 'paid' ? $row['debt'] === 0 && in_array($row['status'], ['paid', 'exempt'], true) : $row['status'] === 'none' && $row['debt'] === 0)));
            $query->whereIn('player_profiles.id', $ids);
        }

        $sort = $filters['sort'] ?? 'full_name';
        $direction = $filters['direction'] ?? ($sort === 'created_at' ? 'desc' : 'asc');
        if ($sort === 'position' && ! empty($filters['team_id'])) {
            $query->orderBy(DB::table('team_players')->select('position')->whereColumn('team_players.player_id', 'player_profiles.id')->where('team_players.club_id', $club->id)->where('team_players.team_id', $filters['team_id'])->limit(1), $direction);
        } elseif ($sort === 'full_name') {
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::connection()->getPdo()->sqliteCreateFunction('fcms_given_name', function ($name) { $parts = preg_split('/\s+/u', trim($name ?? '')); return end($parts) ?: ''; }, 1);
                $query->orderByRaw('fcms_given_name(player_profiles.full_name) '.$direction);
            } else {
                $query->orderByRaw("SUBSTRING_INDEX(TRIM(player_profiles.full_name), ' ', -1) ".$direction);
            }
            $query->orderBy('full_name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        return PlayerResource::collection($query->orderBy('full_name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(Request $request, string $club, int $member): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewRoster', $tenant);

        return new PlayerResource($tenant->players()->with(['team', 'teams', 'user'])->findOrFail($member));
    }

    public function store(PlayerRequest $request, ClubService $service): mixed
    {
        return (new PlayerResource($service->savePlayer($request->attributes->get('club'), $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function import(Request $request, ClubService $service): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('manageRoster', $club);
        $data = $request->validate(['players' => ['required', 'array', 'min:1', 'max:500'], 'players.*' => ['required', 'array:full_name,email,phone,date_of_birth,position,preferred_foot,status,notes']]);
        $rulesRequest = PlayerRequest::create('/', 'POST');
        $rulesRequest->attributes->set('club', $club);
        $validated = []; $errors = []; $emails = [];
        foreach ($data['players'] as $index => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            if (! empty($row['email'])) $row['email'] = mb_strtolower($row['email']);
            $validator = Validator::make($row, $rulesRequest->rules());
            if (! empty($row['email'])) {
                if (isset($emails[$row['email']])) $errors["players.$index.email"] = ['Email bị trùng trong file.'];
                $emails[$row['email']] = true;
            }
            if ($validator->fails()) foreach ($validator->errors()->messages() as $field => $messages) $errors["players.$index.$field"] = $messages;
            else $validated[] = $validator->validated();
        }
        if ($errors) throw ValidationException::withMessages($errors);
        DB::transaction(function () use ($club, $request, $validated, $service) {
            foreach ($validated as $row) $service->savePlayer($club, $request->user(), $row);
        });
        return response()->json(['data' => ['imported' => count($validated)]], 201);
    }

    public function update(PlayerRequest $request, string $club, int $member, ClubService $service): mixed
    {
        $tenant = $request->attributes->get('club');
        $tenant->players()->findOrFail($member);

        return new PlayerResource($service->savePlayer($tenant, $request->user(), $request->validated(), $member));
    }

    public function destroy(Request $request, string $club, int $member, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $avatarPath = DB::transaction(function () use ($tenant, $member, $request, $audit) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $player = $tenant->players()->whereKey($member)->lockForUpdate()->firstOrFail();
            if (DB::table('board_tasks')->join('club_board_members', 'club_board_members.id', '=', 'board_tasks.board_member_id')->where('board_tasks.club_id', $tenant->id)->where('club_board_members.club_id', $tenant->id)->where('club_board_members.player_id', $member)->exists()) throw ValidationException::withMessages(['member' => ['Không thể xóa thành viên BCN đang có công việc.']]);
            if (DB::table('inventory_assignments')->where('club_id', $tenant->id)->where('player_id', $member)->exists()) throw ValidationException::withMessages(['member' => ['Không thể xóa thành viên có lịch sử cấp phát trang bị.']]);
            if (DB::table('match_attendances')->where('club_id', $tenant->id)->where('player_id', $member)->exists()) throw ValidationException::withMessages(['member' => ['Không thể xóa thành viên có lịch sử điểm danh trận đấu.']]);
            if (DB::table('player_match_stats')->where('club_id', $tenant->id)->where('player_id', $member)->exists()) throw ValidationException::withMessages(['member' => ['Không thể xóa thành viên có thống kê trận đấu.']]);
            if (DB::table('team_dues_entries')->where('club_id', $tenant->id)->where('player_id', $member)->exists() || DB::table('team_membership_periods')->join('team_dues_rates', function ($join) { $join->on('team_dues_rates.club_id', '=', 'team_membership_periods.club_id')->on('team_dues_rates.team_id', '=', 'team_membership_periods.team_id'); })->where('team_membership_periods.club_id', $tenant->id)->where('team_membership_periods.player_id', $member)->exists()) throw ValidationException::withMessages(['member' => ['Không thể xóa thành viên có lịch sử quỹ.']]);
            DB::table('team_membership_periods')->where('club_id', $tenant->id)->where('player_id', $member)->delete();
            $audit->record($tenant, $request->user(), 'player.deleted', $player);
            DB::table('teams')->where('club_id', $tenant->id)->where('captain_id', $player->id)->update(['captain_id' => null]);
            DB::table('team_players')->where('club_id', $tenant->id)->where('player_id', $player->id)->delete();
            DB::table('club_awards')->where('club_id', $tenant->id)->where('player_id', $player->id)->update(['player_id' => null]);
            $avatarPath = $player->avatar_path;
            $player->delete();
            return $avatarPath;
        });
        if ($avatarPath) Storage::disk('local')->delete($avatarPath);

        return response()->noContent();
    }
}
