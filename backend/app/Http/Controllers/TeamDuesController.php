<?php

namespace App\Http\Controllers;

use App\Models\FinanceTransaction;
use App\Models\Club;
use App\Services\TeamDuesService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamDuesController extends Controller
{
    public function correct(Request $request, TeamDuesService $service): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageFinance', $club);
        $data = $request->validate([
            'player_id' => ['required', 'integer', Rule::exists('player_profiles', 'id')->where('club_id', $club->id)],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'month' => ['required', 'date_format:Y-m'],
            'expected_status' => ['required', Rule::in(['unpaid', 'paid', 'exempt'])],
            'status' => ['required', Rule::in(['unpaid', 'paid', 'exempt'])],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($request, $club, $service, $data) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $player = $club->players()->findOrFail($data['player_id']); $team = $club->teams()->findOrFail($data['team_id']);
            $month = $data['month'].'-01';
            $entry = DB::table('team_dues_entries')->where('club_id', $club->id)->where('team_id', $team->id)->where('player_id', $player->id)->where('month', $month)->lockForUpdate()->first();
            $old = $entry->status ?? 'unpaid';
            if ($old !== $data['expected_status']) throw ValidationException::withMessages(['status' => ['Trạng thái đã thay đổi. Hãy tải lại dữ liệu trước khi sửa.']]);
            if ($old === $data['status']) throw ValidationException::withMessages(['status' => ['Hãy chọn trạng thái khác trạng thái hiện tại.']]);
            $amount = $entry ? (int) $entry->amount : $service->amount($club->id, $team->id, $player->id, $month);
            if (! $amount) throw ValidationException::withMessages(['month' => ['Không có khoản quỹ cần điều chỉnh cho đội và tháng này.']]);
            $oldTransaction = $entry->finance_transaction_id ?? null; $newTransaction = null;
            if ($old === 'paid') {
                $transaction = FinanceTransaction::where('club_id', $club->id)->whereKey($oldTransaction)->lockForUpdate()->first();
                if (! $transaction || $transaction->type !== 'income' || (int) $transaction->amount < $amount) throw ValidationException::withMessages(['status' => ['Không thể đối chiếu khoản thu gốc; chưa thay đổi dữ liệu.']]);
                $transaction->amount = (int) $transaction->amount - $amount; $transaction->save();
            }
            if ($data['status'] === 'paid') {
                $newTransaction = FinanceTransaction::create(['club_id' => $club->id, 'created_by' => $request->user()->id, 'type' => 'income', 'amount' => $amount, 'occurred_on' => now('Asia/Ho_Chi_Minh')->toDateString(), 'description' => 'Điều chỉnh thu quỹ '.$player->full_name.' · '.$team->name.' · '.$data['month']])->id;
            }
            if ($entry) DB::table('team_dues_entries')->where('id', $entry->id)->delete();
            if ($data['status'] !== 'unpaid') DB::table('team_dues_entries')->insert(['club_id' => $club->id, 'team_id' => $team->id, 'player_id' => $player->id, 'month' => $month, 'status' => $data['status'], 'amount' => $amount, 'finance_transaction_id' => $newTransaction, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('team_dues_adjustments')->insert(['club_id' => $club->id, 'team_id' => $team->id, 'player_id' => $player->id, 'month' => $month, 'old_status' => $old, 'new_status' => $data['status'], 'amount' => $amount, 'old_transaction_id' => $oldTransaction, 'new_transaction_id' => $newTransaction, 'reason' => trim($data['reason']), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->noContent();
    }
    public function rate(Request $request, string $club, int $team): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('manageFinance', $tenant);
        $data = $request->validate(['dues_amount' => ['required', 'integer', 'between:0,1000000000000'], 'dues_month' => ['required', 'date_format:Y-m']]);
        DB::transaction(function () use ($tenant, $team, $data) {
            Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail(); $tenant->teams()->findOrFail($team);
            DB::table('team_dues_rates')->updateOrInsert(['club_id' => $tenant->id, 'team_id' => $team, 'effective_month' => TeamDuesService::rateMonth($data['dues_month'].'-01')], ['amount' => $data['dues_amount'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->noContent();
    }
    public function roster(Request $request, string $club, int $team, TeamDuesService $service): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewFinance', $tenant);
        $tenant->teams()->findOrFail($team);
        return response()->json(['data' => $service->rosterSummary($tenant->id, $team)]);
    }
    public function index(Request $request, TeamDuesService $service): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('viewFinance', $club);
        $input = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = $input['month'].'-01';
        $periods = DB::table('team_membership_periods')->where('club_id', $club->id)->select('player_id', 'team_id')->distinct()->get();
        $players = $club->players()->whereIn('id', $periods->pluck('player_id'))->orderBy('full_name')->get()->keyBy('id');
        $teams = $club->teams()->get()->keyBy('id');
        $summaries = $teams->mapWithKeys(fn ($team) => [$team->id => $service->rosterSummary($club->id, $team->id)]);
        $entries = DB::table('team_dues_entries')->where('club_id', $club->id)->where('month', $month)->get()->keyBy(fn ($x) => $x->team_id.':'.$x->player_id);
        $rows = [];
        foreach ($periods as $period) {
            $player = $players->get($period->player_id); $team = $teams->get($period->team_id);
            if (! $player || ! $team) continue;
            $entry = $entries->get($team->id.':'.$player->id);
            $amount = $service->amount($club->id, $team->id, $player->id, $month);
            if ($amount === null && ! $entry) continue;
            $rows[$player->id] ??= ['player_id' => $player->id, 'name' => $player->full_name, 'date_of_birth' => $player->date_of_birth?->format('Y-m-d'), 'email' => $player->email, 'teams' => [], 'total' => 0, 'debt' => 0];
            $amount = $entry ? (int) $entry->amount : $amount;
            $status = $entry->status ?? ($amount > 0 ? ($month <= CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString() ? 'debt' : 'pending') : 'none');
            $summary = $summaries[$team->id][$player->id] ?? ['debt' => 0, 'months' => 0];
            $rows[$player->id]['teams'][] = ['team_id' => $team->id, 'name' => $team->name, 'amount' => $amount, 'status' => $status, 'debt_total' => $summary['debt'], 'debt_months' => $summary['months']];
            $rows[$player->id]['total'] += $amount;
            if ($status === 'debt' && $month <= CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString()) $rows[$player->id]['debt'] += $amount;
        }
        return response()->json(['data' => array_values($rows)]);
    }

    public function store(Request $request, TeamDuesService $service): mixed
    {
        $club = $request->attributes->get('club'); Gate::authorize('manageFinance', $club);
        $data = $request->validate(['player_id' => ['required', 'integer', Rule::exists('player_profiles', 'id')->where('club_id', $club->id)], 'month' => ['required', 'date_format:Y-m'], 'months' => ['required', 'integer', 'between:1,24'], 'status' => ['required', Rule::in(['paid', 'exempt'])]]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $data['month'], 'Asia/Ho_Chi_Minh');
        DB::transaction(function () use ($club, $request, $data, $start, $service) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $player = $club->players()->findOrFail($data['player_id']);
            $teams = DB::table('team_membership_periods')->where('club_id', $club->id)->where('player_id', $player->id)->distinct()->pluck('team_id');
            $items = []; $total = 0;
            for ($i = 0; $i < $data['months']; $i++) {
                $month = $start->addMonthsNoOverflow($i)->toDateString();
                foreach ($teams as $team) {
                    $amount = $service->amount($club->id, $team, $player->id, $month);
                    if ($amount === null || $amount === 0) continue;
                    if (DB::table('team_dues_entries')->where('club_id', $club->id)->where('team_id', $team)->where('player_id', $player->id)->where('month', $month)->exists()) continue;
                    $items[] = ['club_id' => $club->id, 'team_id' => $team, 'player_id' => $player->id, 'month' => $month, 'status' => $data['status'], 'amount' => $amount, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()];
                    $total += $amount;
                }
            }
            if (! $items) throw ValidationException::withMessages(['month' => ['Không có khoản quỹ cần ghi trong khoảng này.']]);
            $transaction = null;
            if ($data['status'] === 'paid') $transaction = FinanceTransaction::create(['club_id' => $club->id, 'created_by' => $request->user()->id, 'type' => 'income', 'amount' => $total, 'occurred_on' => now('Asia/Ho_Chi_Minh')->toDateString(), 'description' => 'Thu quỹ '.$player->full_name.' từ '.$data['month'].' ('.$data['months'].' tháng)']);
            foreach ($items as $item) { $item['finance_transaction_id'] = $transaction?->id; DB::table('team_dues_entries')->insert($item); }
        });
        return response()->json(['message' => 'Đã ghi quỹ.'], 201);
    }
}
