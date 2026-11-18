<?php

namespace App\Http\Controllers;

use App\Enums\ClubRole;
use Carbon\CarbonImmutable;
use App\Services\TeamDuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubBoardController extends Controller
{
    private function owner(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        abort_unless($club->roleFor($request->user()) === ClubRole::Owner, 403);
        return $club;
    }

    public function index(Request $request): mixed
    {
        $club = $this->owner($request);
        $rows = DB::table('club_board_members as b')->join('player_profiles as p', function ($join) {
            $join->on('p.id', '=', 'b.player_id')->on('p.club_id', '=', 'b.club_id');
        })->where('b.club_id', $club->id)->whereNull('b.archived_at')->orderBy('p.full_name')
            ->select('b.id', 'b.player_id', 'b.title', 'p.full_name', 'p.email', 'p.date_of_birth')->get();
        $rates = DB::table('board_dues_rates as r')->join('teams as t', function ($join) {
            $join->on('t.id', '=', 'r.team_id')->on('t.club_id', '=', 'r.club_id');
        })->where('r.club_id', $club->id)->orderBy('r.effective_month')->orderBy('r.id')
            ->select('r.player_id', 'r.team_id', 'r.amount', 'r.effective_month', 'r.ends_before', 't.name as team_name')->get()->groupBy('player_id');
        $rows->each(function ($row) use ($rates) { $row->dues_rates = ($rates->get($row->player_id) ?? collect())->values(); });
        $shared = DB::table('shared_board_dues_rates as r')->join('teams as t', function ($join) { $join->on('t.id', '=', 'r.team_id')->on('t.club_id', '=', 'r.club_id'); })
            ->where('r.club_id', $club->id)->orderBy('r.effective_month')->orderBy('r.id')->select('r.team_id', 'r.amount', 'r.effective_month', 't.name as team_name')->get();
        return response()->json(['data' => $rows, 'shared_rates' => $shared]);
    }

    public function store(Request $request): mixed
    {
        $club = $this->owner($request);
        abort_unless($club->status === 'active', 403);
        $data = $request->validate(['player_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:120']]);
        return DB::transaction(function () use ($request, $club, $data) {
            DB::table('clubs')->where('id', $club->id)->lockForUpdate()->first();
            abort_unless($club->players()->whereKey($data['player_id'])->exists(), 404);
            $existing = DB::table('club_board_members')->where('club_id', $club->id)->where('player_id', $data['player_id'])->first();
            if ($existing && ! $existing->archived_at) {
                throw ValidationException::withMessages(['player_id' => ['Thành viên đã có trong ban chủ nhiệm.']]);
            }
            if ($existing) { $id = $existing->id; DB::table('club_board_members')->where('id', $id)->update(['title' => trim($data['title']), 'archived_at' => null, 'updated_at' => now()]); }
            else { $id = DB::table('club_board_members')->insertGetId(['club_id' => $club->id, 'player_id' => $data['player_id'], 'title' => trim($data['title']), 'created_at' => now(), 'updated_at' => now()]); }
            DB::table('board_membership_periods')->insert(['club_id' => $club->id, 'player_id' => $data['player_id'], 'starts_month' => CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            return response()->json(['data' => ['id' => $id]], 201);
        });
    }

    public function update(Request $request, string $club, int $boardMember): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $row = DB::table('club_board_members')->where('club_id', $tenant->id)->whereNull('archived_at')->where('id', $boardMember)->first();
        abort_unless($row, 404);
        DB::table('club_board_members')->where('club_id', $tenant->id)->where('id', $boardMember)->update(['title' => trim($data['title']), 'updated_at' => now()]);
        return response()->noContent();
    }

    public function destroy(Request $request, string $club, int $boardMember): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        DB::transaction(function () use ($tenant, $boardMember) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $board = DB::table('club_board_members')->where('club_id', $tenant->id)->whereNull('archived_at')->where('id', $boardMember)->first();
            abort_unless($board, 404);
            $endsBefore = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->addMonthNoOverflow()->toDateString();
            DB::table('board_dues_rates')->where('club_id', $tenant->id)->where('player_id', $board->player_id)->whereNull('ends_before')->update(['ends_before' => $endsBefore, 'updated_at' => now()]);
            DB::table('board_membership_periods')->where('club_id', $tenant->id)->where('player_id', $board->player_id)->whereNull('ends_before')->update(['ends_before' => $endsBefore, 'updated_at' => now()]);
            DB::table('club_board_members')->where('club_id', $tenant->id)->where('id', $boardMember)->update(['archived_at' => now(), 'updated_at' => now()]);
        });
        return response()->noContent();
    }

    public function saveDuesRate(Request $request, string $club, int $boardMember): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['team_id' => ['required', 'integer'], 'amount' => ['required', 'integer', 'between:0,1000000000000'], 'effective_month' => ['required', 'date_format:Y-m']]);
        $month = $data['effective_month'].'-01';
        if ($month < CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString()) {
            throw ValidationException::withMessages(['effective_month' => ['Chọn tháng hiện tại hoặc tháng sau để giữ nguyên lịch sử.']]);
        }
        if (TeamDuesService::rateMonth($month) !== $month) {
            throw ValidationException::withMessages(['effective_month' => ['Từ ngày 3, mức quỹ mới áp dụng sớm nhất từ tháng sau.']]);
        }
        DB::transaction(function () use ($tenant, $boardMember, $data, $month) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $board = DB::table('club_board_members')->where('club_id', $tenant->id)->whereNull('archived_at')->where('id', $boardMember)->first();
            abort_unless($board, 404);
            abort_unless(DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $data['team_id'])->where('player_id', $board->player_id)->exists(), 404);
            $normal = DB::table('team_dues_rates')->where('club_id', $tenant->id)->where('team_id', $data['team_id'])->where('effective_month', '<=', $month)->orderByDesc('effective_month')->value('amount');
            if ($normal === null || (int) $data['amount'] > (int) $normal) throw ValidationException::withMessages(['amount' => ['Mức ưu đãi không được vượt mức quỹ đội tại tháng áp dụng.']]);
            DB::table('board_dues_rates')->insert(['club_id' => $tenant->id, 'team_id' => $data['team_id'], 'player_id' => $board->player_id, 'effective_month' => $month, 'amount' => $data['amount'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->noContent();
    }

    public function saveSharedDuesRate(Request $request): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['team_id' => ['required', 'integer'], 'amount' => ['required', 'integer', 'between:0,1000000000000'], 'effective_month' => ['required', 'date_format:Y-m']]);
        $month = $data['effective_month'].'-01';
        if ($month < CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString() || TeamDuesService::rateMonth($month) !== $month) {
            throw ValidationException::withMessages(['effective_month' => ['Chọn tháng hợp lệ; từ ngày 3, mức mới áp dụng sớm nhất tháng sau.']]);
        }
        DB::transaction(function () use ($tenant, $data, $month) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $tenant->teams()->findOrFail($data['team_id']);
            $normal = DB::table('team_dues_rates')->where('club_id', $tenant->id)->where('team_id', $data['team_id'])->where('effective_month', '<=', $month)->orderByDesc('effective_month')->value('amount');
            if ($normal === null || (int) $data['amount'] > (int) $normal) throw ValidationException::withMessages(['amount' => ['Mức BCN không được vượt mức quỹ đội tại tháng áp dụng.']]);
            DB::table('shared_board_dues_rates')->insert(['club_id' => $tenant->id, 'team_id' => $data['team_id'], 'effective_month' => $month, 'amount' => $data['amount'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->noContent();
    }

    public function tasks(Request $request): mixed
    {
        $tenant = $this->owner($request);
        $rows = DB::table('board_tasks as t')->join('club_board_members as b', function ($join) {
            $join->on('b.id', '=', 't.board_member_id')->on('b.club_id', '=', 't.club_id');
        })->join('player_profiles as p', function ($join) {
            $join->on('p.id', '=', 'b.player_id')->on('p.club_id', '=', 'b.club_id');
        })->where('t.club_id', $tenant->id)->orderBy('t.due_date')->orderByDesc('t.id')
            ->select('t.*', 'p.full_name as assignee_name')->get();
        return response()->json(['data' => $rows]);
    }

    public function saveTask(Request $request, string $club, ?int $task = null): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        $data = $request->validate(['board_member_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'due_date' => ['nullable', 'date_format:Y-m-d'], 'progress' => ['required', 'integer', 'between:0,100']]);
        return DB::transaction(function () use ($tenant, $task, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            abort_unless(DB::table('club_board_members')->where('club_id', $tenant->id)->whereNull('archived_at')->where('id', $data['board_member_id'])->exists(), 404);
            $values = $data + ['updated_at' => now()];
            if ($task !== null) {
                abort_unless(DB::table('board_tasks')->where('club_id', $tenant->id)->where('id', $task)->exists(), 404);
                DB::table('board_tasks')->where('club_id', $tenant->id)->where('id', $task)->update($values);
                return response()->noContent();
            }
            $id = DB::table('board_tasks')->insertGetId($values + ['club_id' => $tenant->id, 'created_at' => now()]);
            return response()->json(['data' => ['id' => $id]], 201);
        });
    }

    public function deleteTask(Request $request, string $club, int $task): mixed
    {
        $tenant = $this->owner($request);
        abort_unless($tenant->status === 'active', 403);
        abort_unless(DB::table('board_tasks')->where('club_id', $tenant->id)->where('id', $task)->delete(), 404);
        return response()->noContent();
    }
}
