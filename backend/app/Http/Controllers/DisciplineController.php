<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Enums\ClubRole;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DisciplineController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        $this->authorizeDiscipline($club, $request);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['open', 'reviewing', 'resolved', 'cancelled'])], 'player_id' => ['nullable', 'integer', 'min:1']]);
        $query = DB::table('discipline_cases as c')->join('player_profiles as p', function ($join) {
            $join->on('p.id', '=', 'c.player_id')->on('p.club_id', '=', 'c.club_id');
        })->where('c.club_id', $club->id)->select('c.*', 'p.full_name as player_name');
        if (! empty($filters['status'])) $query->where('c.status', $filters['status']);
        if (! empty($filters['player_id'])) $query->where('c.player_id', $filters['player_id']);
        return response()->json($query->orderByDesc('c.incident_date')->orderByDesc('c.id')->paginate(25));
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); $this->authorizeDiscipline($club, $request, true);
        $data = $this->validated($request);
        $id = DB::transaction(function () use ($club, $request, $audit, $data) {
            $locked = Club::whereKey($club->id)->lockForUpdate()->firstOrFail(); $this->authorizeDiscipline($locked, $request, true);
            $locked->players()->whereKey($data['player_id'])->firstOrFail();
            $id = DB::table('discipline_cases')->insertGetId($data + ['club_id' => $club->id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->snapshot($club->id, $id, $request->user()->id, 'created', null);
            $audit->record($locked, $request->user(), 'discipline.created', $locked);
            return $id;
        });
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function update(Request $request, string $club, int $case, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); $this->authorizeDiscipline($tenant, $request, true);
        $data = $this->validated($request, true);
        DB::transaction(function () use ($tenant, $case, $request, $audit, $data) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); $this->authorizeDiscipline($locked, $request, true);
            $record = DB::table('discipline_cases')->where('club_id', $tenant->id)->where('id', $case)->lockForUpdate()->first();
            if (! $record) abort(404);
            $locked->players()->whereKey($data['player_id'])->firstOrFail();
            $reason = $data['reason']; unset($data['reason']);
            DB::table('discipline_cases')->where('club_id', $tenant->id)->where('id', $case)->update($data + ['updated_at' => now()]);
            $this->snapshot($tenant->id, $case, $request->user()->id, 'updated', $reason);
            $audit->record($locked, $request->user(), 'discipline.updated', $locked);
        });
        return response()->noContent();
    }

    public function history(Request $request, string $club, int $case): mixed
    {
        $tenant = $request->attributes->get('club'); $this->authorizeDiscipline($tenant, $request);
        if (! DB::table('discipline_cases')->where('club_id', $tenant->id)->where('id', $case)->exists()) abort(404);
        $records = DB::table('discipline_case_history as h')->join('users as u', 'u.id', '=', 'h.actor_id')
            ->where('h.club_id', $tenant->id)->where('h.case_id', $case)
            ->select('h.id', 'h.action', 'h.reason', 'h.snapshot', 'h.created_at', 'u.name as actor_name')->orderByDesc('h.id')->paginate(25);
        $records->getCollection()->transform(function ($row) { $row->snapshot = json_decode($row->snapshot, true); return $row; });
        return response()->json($records);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'player_id' => ['required', 'integer', 'min:1'], 'incident_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['open', 'reviewing', 'resolved', 'cancelled'])],
            'decision' => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn () => $request->input('status') === 'resolved')],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'reason' => [$updating ? 'required' : 'prohibited', 'string', 'max:1000'],
        ]);
    }

    private function authorizeDiscipline(Club $club, Request $request, bool $write = false): void
    {
        abort_unless(in_array($club->roleFor($request->user()), [ClubRole::Owner, ClubRole::CoOwner, ClubRole::GeneralManager, ClubRole::HumanResources], true) && (! $write || $club->status === 'active'), 403);
    }

    private function snapshot(int $club, int $case, int $actor, string $action, ?string $reason): void
    {
        $record = DB::table('discipline_cases')->where('club_id', $club)->where('id', $case)->first();
        DB::table('discipline_case_history')->insert(['club_id' => $club, 'case_id' => $case, 'actor_id' => $actor, 'action' => $action, 'reason' => $reason, 'snapshot' => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
