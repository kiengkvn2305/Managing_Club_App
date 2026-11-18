<?php
namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\PlannedExpense;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlannedExpenseController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewFinance', $club);
        $data = $request->validate(['from' => ['required', 'date_format:Y-m'], 'to' => ['required', 'date_format:Y-m', 'after_or_equal:from']]);
        $first = CarbonImmutable::createFromFormat('!Y-m', $data['from']); $last = CarbonImmutable::createFromFormat('!Y-m', $data['to']);
        if ($first->diffInMonths($last) > 11) throw ValidationException::withMessages(['to' => ['Chỉ xem tối đa 12 tháng mỗi lần.']]);
        $plans = PlannedExpense::where('club_id', $club->id)->where('cancelled', false)->where('effective_from', '<=', $last->toDateString())->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $first->toDateString()))->orderBy('description')->limit(501)->get();
        if ($plans->count() > 500) throw ValidationException::withMessages(['from' => ['Có quá nhiều khoản dự kiến trong kỳ.']]);
        $rows = [];
        for ($month = $first; $month <= $last; $month = $month->addMonthNoOverflow()) foreach ($plans as $plan) {
            $key = $month->toDateString();
            if ($plan->effective_from <= $key && (! $plan->effective_to || $plan->effective_to >= $key)) $rows[] = ['id' => $plan->id, 'month' => $month->format('Y-m'), 'description' => $plan->description, 'amount' => $plan->amount === null ? null : (int) $plan->amount, 'notes' => $plan->notes, 'effective_from' => substr($plan->effective_from, 0, 7), 'effective_to' => $plan->effective_to ? substr($plan->effective_to, 0, 7) : null];
        }
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageFinance', $club);
        $data = $this->validatePlan($request);
        $plan = DB::transaction(function () use ($club, $request, $data, $audit) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $plan = PlannedExpense::create(['club_id' => $club->id, 'created_by' => $request->user()->id, 'description' => $data['description'], 'amount' => $data['amount'] ?? null, 'notes' => $data['notes'] ?? null, 'effective_from' => $data['month'].'-01']);
            $audit->record($club, $request->user(), 'planned_expense.created', $plan); return $plan;
        });
        return response()->json(['data' => ['id' => $plan->id]], 201);
    }

    public function update(Request $request, string $club, int $plan, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant); $data = $this->validatePlan($request);
        DB::transaction(function () use ($tenant, $request, $plan, $data, $audit) {
            Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $current = PlannedExpense::where('club_id', $tenant->id)->whereKey($plan)->lockForUpdate()->firstOrFail();
            $month = $data['month'].'-01'; $this->assertApplicable($current, $month);
            if ($current->effective_from === $month) {
                $current->update(['description' => $data['description'], 'amount' => $data['amount'] ?? null, 'notes' => $data['notes'] ?? null]);
                $updated = $current;
            } else {
                $oldEnd = $current->effective_to;
                $current->update(['effective_to' => CarbonImmutable::parse($month)->subMonthNoOverflow()->toDateString()]);
                $updated = PlannedExpense::create(['club_id' => $tenant->id, 'created_by' => $request->user()->id, 'description' => $data['description'], 'amount' => $data['amount'] ?? null, 'notes' => $data['notes'] ?? null, 'effective_from' => $month, 'effective_to' => $oldEnd]);
            }
            $audit->record($tenant, $request->user(), 'planned_expense.updated', $updated);
        });
        return response()->noContent();
    }

    public function stop(Request $request, string $club, int $plan, AuditService $audit): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        DB::transaction(function () use ($tenant, $request, $plan, $data, $audit) {
            Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $current = PlannedExpense::where('club_id', $tenant->id)->whereKey($plan)->lockForUpdate()->firstOrFail();
            $month = $data['month'].'-01'; $this->assertApplicable($current, $month);
            $current->update($month === $current->effective_from ? ['cancelled' => true] : ['effective_to' => CarbonImmutable::parse($month)->subMonthNoOverflow()->toDateString()]);
            $audit->record($tenant, $request->user(), 'planned_expense.stopped', $current);
        });
        return response()->noContent();
    }

    private function validatePlan(Request $request): array
    {
        return $request->validate(['description' => ['required', 'string', 'max:255'], 'amount' => ['nullable', 'integer', 'between:0,1000000000000'], 'notes' => ['nullable', 'string', 'max:2000'], 'month' => ['required', 'date_format:Y-m']]);
    }

    private function assertApplicable(PlannedExpense $plan, string $month): void
    {
        if ($plan->cancelled || $plan->effective_from > $month || ($plan->effective_to && $plan->effective_to < $month)) throw ValidationException::withMessages(['month' => ['Khoản dự kiến đã thay đổi hoặc không áp dụng trong tháng này. Hãy tải lại.']]);
    }
}
