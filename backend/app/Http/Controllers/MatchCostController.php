<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMatch;
use App\Models\MatchCost;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchCostController extends Controller
{
    public function index(Request $request, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewFinance', $tenant);
        $game = ClubMatch::where('club_id', $tenant->id)->findOrFail($match);
        $split = MatchCost::where('club_id', $tenant->id)->where('match_id', $game->id)->first();
        $rows = $split ? DB::table('match_cost_receivables as r')->join('player_profiles as p', 'p.id', '=', 'r.player_id')->where('r.club_id', $tenant->id)->where('r.split_id', $split->id)->select('r.player_id', 'p.full_name', 'r.amount', 'r.exempt', 'r.weight')->orderBy('p.full_name')->get() : [];
        $players = DB::table('player_profiles as p')->leftJoin('team_players as tp', function ($join) use ($tenant, $game) {
            $join->on('tp.player_id', '=', 'p.id')->where('tp.club_id', $tenant->id)->where('tp.team_id', $game->team_id);
        })->leftJoin('match_attendances as a', function ($join) use ($tenant, $game) {
            $join->on('a.player_id', '=', 'p.id')->where('a.club_id', $tenant->id)->where('a.match_id', $game->id);
        })->where('p.club_id', $tenant->id)->where(fn ($q) => $q->whereNotNull('tp.player_id')->orWhereNotNull('a.id'))
            ->select('p.id as player_id', 'p.full_name', 'p.date_of_birth', 'p.email', 'a.attendance')->orderBy('p.full_name')->orderBy('p.id')->get();
        return response()->json(['data' => ['split' => $split, 'receivables' => $rows, 'players' => $players]]);
    }

    public function preview(Request $request, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageFinance', $tenant);
        $game = ClubMatch::where('club_id', $tenant->id)->findOrFail($match);
        return response()->json(['data' => $this->calculate($request, $tenant, $game)]);
    }

    public function confirm(Request $request, AuditService $audit, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageFinance', $tenant);
        $result = DB::transaction(function () use ($tenant, $request, $match, $audit) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageFinance', $locked);
            $game = ClubMatch::where('club_id', $locked->id)->whereKey($match)->lockForUpdate()->firstOrFail();
            $calculation = $this->calculate($request, $locked, $game);
            $hash = hash('sha256', json_encode($calculation, JSON_THROW_ON_ERROR));
            $existing = MatchCost::where('club_id', $locked->id)->where('match_id', $game->id)->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) abort(409, 'Trận đã có khoản chia; không thể ghi đè.');
                return ['id' => $existing->id, 'replayed' => true];
            }
            $split = MatchCost::create($calculation['costs'] + ['club_id' => $locked->id, 'match_id' => $game->id, 'method' => $calculation['method'], 'total' => $calculation['total'], 'payload_hash' => $hash, 'created_by' => $request->user()->id]);
            foreach ($calculation['receivables'] as $row) DB::table('match_cost_receivables')->insert($row + ['club_id' => $locked->id, 'split_id' => $split->id, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($locked, $request->user(), 'match.cost_split_confirmed', $split);
            return ['id' => $split->id, 'replayed' => false];
        });
        return response()->json(['data' => $result], $result['replayed'] ? 200 : 201);
    }

    private function calculate(Request $request, Club $club, ClubMatch $game): array
    {
        $data = $request->validate([
            'stadium' => ['required', 'integer', 'between:0,1000000000'], 'referee' => ['required', 'integer', 'between:0,1000000000'],
            'water' => ['required', 'integer', 'between:0,1000000000'], 'equipment' => ['required', 'integer', 'between:0,1000000000'],
            'other' => ['required', 'integer', 'between:0,1000000000'], 'sponsorship' => ['required', 'integer', 'between:0,1000000000'],
            'method' => ['required', Rule::in(['equal', 'weighted', 'custom'])], 'players' => ['required', 'array', 'min:1', 'max:100'],
            'players.*.player_id' => ['required', 'integer', 'min:1', 'distinct'], 'players.*.exempt' => ['required', 'boolean'],
            'players.*.weight' => ['nullable', 'integer', 'between:1,1000'], 'players.*.amount' => ['nullable', 'integer', 'between:0,5000000000'],
        ]);
        if ($game->status === 'cancelled') throw ValidationException::withMessages(['match' => ['Không thể chia phí trận đã hủy.']]);
        $costs = []; foreach (['stadium', 'referee', 'water', 'equipment', 'other', 'sponsorship'] as $key) $costs[$key] = (int) $data[$key];
        $total = $costs['stadium'] + $costs['referee'] + $costs['water'] + $costs['equipment'] + $costs['other'] - $costs['sponsorship'];
        if ($total < 0) throw ValidationException::withMessages(['sponsorship' => ['Tài trợ không được vượt tổng chi phí.']]);
        $ids = array_map(fn ($p) => (int) $p['player_id'], $data['players']);
        $allowed = DB::table('player_profiles as p')->where('p.club_id', $club->id)->whereIn('p.id', $ids)->where(function ($q) use ($club, $game) {
            $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('team_players as tp')->whereColumn('tp.player_id', 'p.id')->where('tp.club_id', $club->id)->where('tp.team_id', $game->team_id))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('match_attendances as a')->whereColumn('a.player_id', 'p.id')->where('a.club_id', $club->id)->where('a.match_id', $game->id));
        })->pluck('p.id')->map(fn ($id) => (int) $id)->all();
        if (count($allowed) !== count($ids)) throw ValidationException::withMessages(['players' => ['Thành viên phải thuộc đội hoặc có lịch sử điểm danh trận này.']]);
        $rows = [];
        foreach ($data['players'] as $player) {
            $exempt = (bool) $player['exempt'];
            if ($data['method'] === 'custom' && ! $exempt && (! array_key_exists('amount', $player) || $player['amount'] === null)) throw ValidationException::withMessages(['players' => ['Cần nhập số tiền cho mỗi người không được miễn.']]);
            if ($data['method'] === 'weighted' && ! $exempt && empty($player['weight'])) throw ValidationException::withMessages(['players' => ['Cần nhập trọng số cho mỗi người không được miễn.']]);
            $rows[] = ['player_id' => (int) $player['player_id'], 'amount' => $exempt ? 0 : ($data['method'] === 'custom' ? (int) $player['amount'] : 0), 'exempt' => $exempt, 'weight' => $data['method'] === 'weighted' && ! $exempt ? (int) $player['weight'] : 1];
        }
        usort($rows, fn ($a, $b) => $a['player_id'] <=> $b['player_id']);
        $weightSum = array_sum(array_map(fn ($r) => $r['exempt'] ? 0 : $r['weight'], $rows));
        if (! $weightSum && $total > 0) throw ValidationException::withMessages(['players' => ['Cần ít nhất một người đóng phí.']]);
        if ($data['method'] === 'custom') {
            if (array_sum(array_column($rows, 'amount')) !== $total) throw ValidationException::withMessages(['players' => ['Tổng tiền từng người phải bằng chi phí sau tài trợ.']]);
        } elseif ($weightSum) {
            $used = 0; $remainders = [];
            foreach ($rows as $i => &$row) if (! $row['exempt']) { $product = $total * $row['weight']; $row['amount'] = intdiv($product, $weightSum); $used += $row['amount']; $remainders[] = ['index' => $i, 'remainder' => $product % $weightSum, 'player_id' => $row['player_id']]; } unset($row);
            usort($remainders, fn ($a, $b) => ($b['remainder'] <=> $a['remainder']) ?: ($a['player_id'] <=> $b['player_id']));
            for ($i = 0; $i < $total - $used; $i++) $rows[$remainders[$i]['index']]['amount']++;
        }
        return ['costs' => $costs, 'method' => $data['method'], 'total' => $total, 'receivables' => $rows];
    }
}
