<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TeamDuesService
{
    public static function startMonth(string $joined): string
    {
        $date = CarbonImmutable::parse($joined);
        return ($date->day < 15 ? $date : $date->addMonthNoOverflow())->startOfMonth()->toDateString();
    }

    public static function rateMonth(string $requested, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::now('Asia/Ho_Chi_Minh');
        $earliest = ($today->day < 3 ? $today : $today->addMonthNoOverflow())->startOfMonth();
        $month = CarbonImmutable::parse($requested)->startOfMonth();
        return $month->lessThan($earliest) ? $earliest->toDateString() : $month->toDateString();
    }

    public function joined(int $club, int $team, int $player): void
    {
        DB::table('team_membership_periods')->insert(['club_id' => $club, 'team_id' => $team, 'player_id' => $player, 'joined_on' => now('Asia/Ho_Chi_Minh')->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function left(int $club, int $team, int $player): void
    {
        DB::table('team_membership_periods')->where('club_id', $club)->where('team_id', $team)->where('player_id', $player)->whereNull('left_on')->update(['left_on' => now('Asia/Ho_Chi_Minh')->toDateString(), 'updated_at' => now()]);
    }

    public function amount(int $club, int $team, int $player, string $month): ?int
    {
        $periods = DB::table('team_membership_periods')->where('club_id', $club)->where('team_id', $team)->where('player_id', $player)->get();
        $eligible = $periods->contains(fn ($p) => self::startMonth($p->joined_on) <= $month && (! $p->left_on || substr($p->left_on, 0, 7) >= substr($month, 0, 7)));
        if (! $eligible) return null;
        $amount = DB::table('team_dues_rates')->where('club_id', $club)->where('team_id', $team)->where('effective_month', '<=', $month)->orderByDesc('effective_month')->value('amount');
        if ($amount === null) return null;
        $board = DB::table('board_membership_periods')->where('club_id', $club)->where('player_id', $player)->where('starts_month', '<=', $month)->where(fn ($q) => $q->whereNull('ends_before')->orWhere('ends_before', '>', $month))->exists();
        if ($board) {
            $shared = DB::table('shared_board_dues_rates')->where('club_id', $club)->where('team_id', $team)->where('effective_month', '<=', $month)->orderByDesc('effective_month')->orderByDesc('id')->value('amount');
            if ($shared !== null) return min((int) $amount, (int) $shared);
        }
        $special = DB::table('board_dues_rates')->where('club_id', $club)->where('team_id', $team)->where('player_id', $player)->where('effective_month', '<=', $month)->where(fn ($q) => $q->whereNull('ends_before')->orWhere('ends_before', '>', $month))->orderByDesc('effective_month')->orderByDesc('id')->value('amount');
        return $special === null ? (int) $amount : min((int) $amount, (int) $special);
    }

    public function rosterSummary(int $club, int $team): array
    {
        $periods = DB::table('team_membership_periods')->where('club_id', $club)->where('team_id', $team)->get()->groupBy('player_id');
        $rates = DB::table('team_dues_rates')->where('club_id', $club)->where('team_id', $team)->orderBy('effective_month')->get();
        $specialRates = DB::table('board_dues_rates')->where('club_id', $club)->where('team_id', $team)->orderBy('effective_month')->orderBy('id')->get()->groupBy('player_id');
        $boardPeriods = DB::table('board_membership_periods')->where('club_id', $club)->get()->groupBy('player_id');
        $sharedRates = DB::table('shared_board_dues_rates')->where('club_id', $club)->where('team_id', $team)->orderBy('effective_month')->orderBy('id')->get();
        $entries = DB::table('team_dues_entries')->where('club_id', $club)->where('team_id', $team)->get()->keyBy(fn ($x) => $x->player_id.':'.$x->month);
        $now = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfMonth(); $result = [];
        foreach ($periods as $player => $history) {
            $first = $history->min('joined_on');
            $firstRate = $rates->first()?->effective_month;
            if (! $firstRate) { $result[$player] = ['debt' => 0, 'months' => 0, 'status' => 'none']; continue; }
            $cursor = CarbonImmutable::parse(max(self::startMonth($first), $firstRate), 'Asia/Ho_Chi_Minh')->startOfMonth();
            $debt = 0; $months = 0; $current = 'none';
            while ($cursor <= $now) {
                $month = $cursor->toDateString();
                $eligible = $history->contains(fn ($p) => self::startMonth($p->joined_on) <= $month && (! $p->left_on || substr($p->left_on, 0, 7) >= substr($month, 0, 7)));
                $rate = $rates->where('effective_month', '<=', $month)->last(); $entry = $entries->get($player.':'.$month);
                $special = ($specialRates->get($player) ?? collect())->filter(fn ($r) => $r->effective_month <= $month && (! $r->ends_before || $r->ends_before > $month))->last();
                $board = ($boardPeriods->get($player) ?? collect())->contains(fn ($p) => $p->starts_month <= $month && (! $p->ends_before || $p->ends_before > $month));
                $shared = $board ? $sharedRates->where('effective_month', '<=', $month)->last() : null;
                $amount = $rate ? ($shared ? min((int) $rate->amount, (int) $shared->amount) : ($special ? min((int) $rate->amount, (int) $special->amount) : (int) $rate->amount)) : 0;
                if ($eligible && $amount > 0 && ! $entry) { $debt += $amount; $months++; }
                if ($month === $now->toDateString()) $current = $entry->status ?? ($eligible && $amount > 0 ? 'debt' : 'none');
                $cursor = $cursor->addMonthNoOverflow();
            }
            $upcoming = $entries->filter(fn ($entry) => (int) $entry->player_id === (int) $player && $entry->month > $now->toDateString() && in_array($entry->status, ['paid', 'exempt'], true))->sortBy('month')->map(fn ($entry) => ['month' => substr($entry->month, 0, 7), 'status' => $entry->status])->values()->all();
            $result[$player] = ['debt' => $debt, 'months' => $months, 'status' => $current, 'upcoming' => $upcoming];
        }
        return $result;
    }
}
