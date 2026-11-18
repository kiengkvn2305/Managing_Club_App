<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    private function approval(Request $request, $club): array
    {
        $membership = $club->memberships()->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $vice = $membership && $membership->role->value !== 'owner' && ($membership->role->value === 'co_owner' || $membership->title === 'Phó chủ nhiệm CLB');
        return ['approval_status' => $vice ? 'pending' : 'approved', 'submitted_by' => $request->user()->id];
    }

    private function visible($query, Request $request, $club)
    {
        if ($club->roleFor($request->user())?->value === 'owner') return $query;
        return $query->where(fn ($q) => $q->where('approval_status', 'approved')->orWhere('submitted_by', $request->user()->id));
    }
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewActivities', $club);
        $celebrations = $this->visible(DB::table('club_celebrations')->where('club_id', $club->id), $request, $club)->orderByDesc('event_date')->get();
        $birthdays = $club->players()->whereNotNull('date_of_birth')->orderBy('full_name')->get(['id', 'full_name', 'date_of_birth'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name, 'date_of_birth' => $p->date_of_birth?->format('Y-m-d')]);
        $trainings = $this->visible(DB::table('team_trainings')->where('club_id', $club->id), $request, $club)->orderByDesc('training_date')->orderByDesc('id')->get()->map(function ($row) { $row->attendance = json_decode($row->attendance, true); return $row; });
        $awards = $this->visible(DB::table('club_awards')->where('club_id', $club->id), $request, $club)->orderByDesc('award_year')->orderByDesc('id')->get();
        $players = $club->players()->orderBy('full_name')->get(['id', 'full_name']);
        return response()->json(['data' => compact('celebrations', 'birthdays', 'trainings', 'awards', 'players')]);
    }

    public function celebration(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageActivities', $club);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'event_date' => ['required', 'date_format:Y-m-d'], 'message' => ['nullable', 'string', 'max:2000']]);
        $id = DB::table('club_celebrations')->insertGetId($data + $this->approval($request, $club) + ['club_id' => $club->id, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function training(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageActivities', $club);
        $data = $request->validate(['team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)], 'training_date' => ['required', 'date_format:Y-m-d'], 'training_time' => ['required', 'date_format:H:i'], 'location' => ['required', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $approval = $this->approval($request, $club);
        $id = DB::transaction(function () use ($club, $data, $approval) {
            DB::table('clubs')->where('id', $club->id)->lockForUpdate()->first();
            $team = $club->teams()->findOrFail($data['team_id']);
            return DB::table('team_trainings')->insertGetId($data + $approval + ['club_id' => $club->id, 'team_name' => $team->name, 'attendance' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function attendance(Request $request, string $club, int $training): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['players' => ['present', 'array', 'max:500'], 'players.*.player_id' => ['required', 'integer', 'distinct'], 'players.*.status' => ['required', Rule::in(['present', 'absent', 'excused'])]]);
        DB::transaction(function () use ($tenant, $training, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $session = DB::table('team_trainings')->where('club_id', $tenant->id)->where('id', $training)->first(); abort_unless($session, 404);
            if ($session->approval_status !== 'approved' || $session->training_status !== 'scheduled') throw ValidationException::withMessages(['players' => ['Chỉ điểm danh lịch tập đã duyệt và đang diễn ra theo kế hoạch.']]);
            if (! $session->team_id) throw ValidationException::withMessages(['players' => ['Đội bóng không còn hoạt động.']]);
            $ids = array_column($data['players'], 'player_id');
            $names = DB::table('team_players')->join('player_profiles', 'player_profiles.id', '=', 'team_players.player_id')->where('team_players.club_id', $tenant->id)->where('team_players.team_id', $session->team_id)->whereIn('team_players.player_id', $ids)->pluck('player_profiles.full_name', 'team_players.player_id');
            if ($names->count() !== count($ids)) throw ValidationException::withMessages(['players' => ['Chỉ điểm danh thành viên trong đội.']]);
            $attendance = array_map(fn ($p) => ['player_id' => $p['player_id'], 'name' => $names[$p['player_id']], 'status' => $p['status']], $data['players']);
            $currentIds = DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $session->team_id)->pluck('player_id')->all();
            foreach (json_decode($session->attendance, true) ?: [] as $old) if (! in_array($old['player_id'], $currentIds)) $attendance[] = $old;
            DB::table('team_trainings')->where('club_id', $tenant->id)->where('id', $training)->update(['attendance' => json_encode($attendance, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        });
        return response()->noContent();
    }

    public function award(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageActivities', $club);
        $data = $request->validate(['player_id' => ['required', 'integer', Rule::exists('player_profiles', 'id')->where('club_id', $club->id)], 'award_year' => ['required', 'integer', 'between:1900,2100'], 'title' => ['required', 'string', 'max:160'], 'gift' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $approval = $this->approval($request, $club);
        $id = DB::transaction(function () use ($club, $data, $approval) {
            DB::table('clubs')->where('id', $club->id)->lockForUpdate()->first();
            $player = $club->players()->findOrFail($data['player_id']);
            return DB::table('club_awards')->insertGetId($data + $approval + ['club_id' => $club->id, 'player_name' => $player->full_name, 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function change(Request $request, string $club, string $kind, int $id): mixed
    {
        $tenant = $request->attributes->get('club'); abort_unless($tenant->roleFor($request->user())?->value === 'owner' && $tenant->status === 'active', 403);
        $tables = ['celebrations' => 'club_celebrations', 'trainings' => 'team_trainings', 'awards' => 'club_awards'];
        abort_unless(isset($tables[$kind]), 404); $table = $tables[$kind];
        $target = DB::table($table)->where('club_id', $tenant->id)->where('id', $id)->first(); abort_unless($target, 404);
        if ($request->isMethod('delete')) { DB::table($table)->where('club_id', $tenant->id)->where('id', $id)->delete(); return response()->noContent(); }
        $rules = match ($kind) {
            'celebrations' => ['title' => ['required', 'string', 'max:160'], 'event_date' => ['required', 'date_format:Y-m-d'], 'message' => ['nullable', 'string', 'max:2000']],
            'trainings' => ['team_id' => ['sometimes', 'required', 'integer', Rule::exists('teams', 'id')->where('club_id', $tenant->id)], 'training_date' => ['required', 'date_format:Y-m-d'], 'training_time' => ['required', 'date_format:H:i'], 'location' => ['required', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000'], 'training_status' => ['required', Rule::in(['scheduled', 'cancelled', 'postponed'])]],
            'awards' => ['player_id' => ['sometimes', 'required', 'integer', Rule::exists('player_profiles', 'id')->where('club_id', $tenant->id)], 'award_year' => ['required', 'integer', 'between:1900,2100'], 'title' => ['required', 'string', 'max:160'], 'gift' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']],
        };
        $data = $request->validate($rules); $data['updated_at'] = now();
        if (isset($data['team_id']) && (int) $data['team_id'] !== (int) $target->team_id) {
            if (count(json_decode($target->attendance, true) ?: [])) throw ValidationException::withMessages(['team_id' => ['Không thể đổi đội khi buổi tập đã có điểm danh.']]);
            $data['team_name'] = $tenant->teams()->findOrFail($data['team_id'])->name;
        }
        if (isset($data['player_id'])) $data['player_name'] = $tenant->players()->findOrFail($data['player_id'])->full_name;
        DB::table($table)->where('club_id', $tenant->id)->where('id', $id)->update($data);
        return response()->noContent();
    }

    public function approve(Request $request, string $club, string $kind, int $id): mixed
    {
        $tenant = $request->attributes->get('club'); abort_unless($tenant->roleFor($request->user())?->value === 'owner' && $tenant->status === 'active', 403);
        $tables = ['celebrations' => 'club_celebrations', 'trainings' => 'team_trainings', 'awards' => 'club_awards'];
        abort_unless(isset($tables[$kind]), 404);
        $updated = DB::table($tables[$kind])->where('club_id', $tenant->id)->where('id', $id)->where('approval_status', 'pending')->update(['approval_status' => 'approved', 'updated_at' => now()]);
        abort_unless($updated, 404); return response()->noContent();
    }
}
