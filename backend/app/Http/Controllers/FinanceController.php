<?php
namespace App\Http\Controllers;

use App\Models\FinanceTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewFinance', $club);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]), 'per_page' => ['nullable', 'integer', 'between:1,100'], 'group' => ['nullable', Rule::in(['month', 'year'])]]);
        $base = FinanceTransaction::where('club_id', $club->id)->when(! empty($filters['from']), fn ($q) => $q->whereDate('occurred_on', '>=', $filters['from']))->when(! empty($filters['to']), fn ($q) => $q->whereDate('occurred_on', '<=', $filters['to']));
        $totals = (clone $base)->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');
        $income = (int) ($totals['income'] ?? 0);
        $expense = (int) ($totals['expense'] ?? 0);
        $transactions = (clone $base)->orderByDesc('occurred_on')->orderByDesc('id')->paginate($filters['per_page'] ?? 30);
        $items = $transactions->getCollection()->map(function ($entry) { $item = $entry->toArray(); $item['has_receipt'] = (bool) $entry->receipt_path; return $item; });
        $length = ($filters['group'] ?? 'month') === 'year' ? 4 : 7;
        $chart = (clone $base)->selectRaw("substr(occurred_on, 1, $length) as period, type, SUM(amount) as total")->groupBy('period', 'type')->orderBy('period')->get();
        return response()->json(['data' => $items, 'meta' => ['current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'total' => $transactions->total()], 'summary' => ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense], 'chart' => $chart]);
    }

    public function store(Request $request, AuditService $audit): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('manageFinance', $club);
        $data = $request->validate(['type' => ['required', Rule::in(['income', 'expense'])], 'amount' => ['required', 'integer', 'between:1,1000000000000'], 'occurred_on' => ['required', 'date_format:Y-m-d'], 'description' => ['required', 'string', 'max:255'], 'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $path = $request->hasFile('receipt') ? $request->file('receipt')->store('finance-receipts/'.$club->id, 'local') : null;
        if ($request->hasFile('receipt') && ! $path) throw ValidationException::withMessages(['receipt' => ['Không thể lưu ảnh xác nhận.']]);
        unset($data['receipt']);
        try { $entry = DB::transaction(function () use ($club, $request, $data, $audit, $path) {
            $entry = FinanceTransaction::create($data + ['club_id' => $club->id, 'created_by' => $request->user()->id, 'receipt_path' => $path]);
            $audit->record($club, $request->user(), 'finance.created', $entry);
            return $entry;
        }); } catch (\Throwable $e) { if ($path) Storage::disk('local')->delete($path); throw $e; }
        return response()->json(['data' => $entry], 201);
    }

    public function receipt(Request $request, string $club, int $entry): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewFinance', $tenant);
        $item = FinanceTransaction::where('club_id', $tenant->id)->findOrFail($entry);
        abort_unless($item->receipt_path && Storage::disk('local')->exists($item->receipt_path), 404);
        return Storage::disk('local')->response($item->receipt_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
