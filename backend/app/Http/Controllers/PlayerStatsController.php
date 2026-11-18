<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMatch;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlayerStatsController extends Controller
{
    public function match(Request $request, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewRoster', $tenant);
        $game = ClubMatch::where('club_id', $tenant->id)->findOrFail($match);
        $players = DB::table('player_profiles as p')
            ->leftJoin('team_players as tp', function ($join) use ($tenant, $game) {
                $join->on('tp.player_id', '=', 'p.id')->where('tp.club_id', $tenant->id)->where('tp.team_id', $game->team_id);
            })->leftJoin('player_match_stats as s', function ($join) use ($tenant, $game) {
                $join->on('s.player_id', '=', 'p.id')->where('s.club_id', $tenant->id)->where('s.match_id', $game->id);
            })->where('p.club_id', $tenant->id)
            ->where(fn ($q) => $q->whereNotNull('tp.player_id')->orWhereNotNull('s.id'))
            ->select('p.id as player_id', 'p.full_name', 'p.date_of_birth', 'p.email', 'tp.position', 'tp.jersey_number', 'tp.player_id as current_team_player', 's.id as stat_id', 's.goals', 's.assists', 's.yellow_cards', 's.red_cards', 's.goals_conceded', 's.rating')
            ->orderBy('p.full_name')->orderBy('p.id')->get();
        return response()->json(['data' => $players]);
    }

    public function save(Request $request, AuditService $audit, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1'],
            'goals' => ['required', 'integer', 'between:0,999'],
            'assists' => ['required', 'integer', 'between:0,999'],
            'yellow_cards' => ['required', 'integer', 'between:0,2'],
            'red_cards' => ['required', 'integer', 'between:0,1'],
            'goals_conceded' => ['nullable', 'integer', 'between:0,999'],
            'rating' => ['nullable', 'numeric', 'between:0,10', 'decimal:0,1'],
        ]);
        DB::transaction(function () use ($tenant, $match, $data, $request, $audit) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageRoster', $locked);
            $game = ClubMatch::where('club_id', $locked->id)->whereKey($match)->lockForUpdate()->firstOrFail();
            if ($game->status !== 'completed') throw ValidationException::withMessages(['match' => ['Chỉ ghi thống kê cho trận đã kết thúc.']]);
            $existing = DB::table('player_match_stats')->where('club_id', $locked->id)->where('match_id', $game->id)->where('player_id', $data['player_id'])->exists();
            $current = DB::table('team_players')->where('club_id', $locked->id)->where('team_id', $game->team_id)->where('player_id', $data['player_id'])->exists();
            $belongs = DB::table('player_profiles')->where('club_id', $locked->id)->where('id', $data['player_id'])->exists();
            if (! $belongs || (! $current && ! $existing)) throw ValidationException::withMessages(['player_id' => ['Thành viên không thuộc đội của trận đấu.']]);
            $otherGoals = DB::table('player_match_stats')->where('club_id', $locked->id)->where('match_id', $game->id)->where('player_id', '!=', $data['player_id'])->sum('goals');
            if ($otherGoals + $data['goals'] > $game->our_score) throw ValidationException::withMessages(['goals' => ['Tổng bàn thắng cầu thủ không thể lớn hơn số bàn của đội.']]);
            $otherAssists = DB::table('player_match_stats')->where('club_id', $locked->id)->where('match_id', $game->id)->where('player_id', '!=', $data['player_id'])->sum('assists');
            if ($otherAssists + $data['assists'] > $game->our_score) throw ValidationException::withMessages(['assists' => ['Tổng kiến tạo không thể lớn hơn số bàn của đội.']]);
            if (($data['goals_conceded'] ?? 0) > $game->opponent_score) throw ValidationException::withMessages(['goals_conceded' => ['Số bàn thủng lưới không thể lớn hơn tỷ số đối thủ.']]);
            DB::table('player_match_stats')->updateOrInsert(
                ['match_id' => $game->id, 'player_id' => $data['player_id']],
                ['club_id' => $locked->id, 'goals' => $data['goals'], 'assists' => $data['assists'], 'yellow_cards' => $data['yellow_cards'], 'red_cards' => $data['red_cards'], 'goals_conceded' => $data['goals_conceded'] ?? null, 'rating' => $data['rating'] ?? null, 'updated_at' => now(), ...($existing ? [] : ['created_at' => now()])]
            );
            $audit->record($locked, $request->user(), 'match.player_stats_updated', $game);
        });
        return response()->json(['data' => ['saved' => true]]);
    }

    public function summary(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewRoster', $club);
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'team_id' => ['nullable', 'integer', 'min:1'], 'player_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $rows = DB::table('player_match_stats as s')
            ->join('club_matches as m', function ($join) { $join->on('m.id', '=', 's.match_id')->on('m.club_id', '=', 's.club_id'); })
            ->join('player_profiles as p', function ($join) { $join->on('p.id', '=', 's.player_id')->on('p.club_id', '=', 's.club_id'); })
            ->join('teams as t', function ($join) { $join->on('t.id', '=', 'm.team_id')->on('t.club_id', '=', 'm.club_id'); })
            ->where('s.club_id', $club->id)->where('m.status', 'completed')->whereBetween('m.match_date', [$data['from'], $data['to']])
            ->when($data['team_id'] ?? null, fn ($q, $id) => $q->where('m.team_id', $id))
            ->when($data['player_id'] ?? null, fn ($q, $id) => $q->where('s.player_id', $id))
            ->select('s.*', 'm.team_id', 'm.match_date', 'p.full_name', 'p.date_of_birth', 'p.email', 't.name as team_name')->get();
        $players = $rows->groupBy('player_id')->map(function ($items) {
            $first = $items->first();
            return ['player_id' => $first->player_id, 'full_name' => $first->full_name, 'date_of_birth' => $first->date_of_birth, 'email' => $first->email,
                'matches_played' => $items->count(), 'goals' => $items->sum('goals'), 'assists' => $items->sum('assists'),
                'yellow_cards' => $items->sum('yellow_cards'), 'red_cards' => $items->sum('red_cards'),
                'goalkeeper_matches' => $items->whereNotNull('goals_conceded')->count(),
                'goals_conceded' => $items->whereNotNull('goals_conceded')->sum('goals_conceded'),
                'clean_sheets' => $items->filter(fn ($item) => $item->goals_conceded !== null && (int) $item->goals_conceded === 0)->count(),
                'average_rating' => $items->whereNotNull('rating')->count() ? round($items->whereNotNull('rating')->avg('rating'), 1) : null];
        })->sortByDesc('goals')->values();
        $teams = $rows->groupBy('team_id')->map(fn ($items) => ['team_id' => $items->first()->team_id, 'team_name' => $items->first()->team_name,
            'player_appearances' => $items->count(), 'matches' => $items->pluck('match_id')->unique()->count(), 'goals' => $items->sum('goals'), 'assists' => $items->sum('assists'), 'yellow_cards' => $items->sum('yellow_cards'), 'red_cards' => $items->sum('red_cards')])->values();
        return response()->json(['data' => ['players' => $players, 'teams' => $teams, 'club' => ['matches' => $rows->pluck('match_id')->unique()->count(), 'player_appearances' => $rows->count(), 'goals' => $rows->sum('goals'), 'assists' => $rows->sum('assists'), 'yellow_cards' => $rows->sum('yellow_cards'), 'red_cards' => $rows->sum('red_cards')]]]);
    }

    public function destroy(Request $request, AuditService $audit, string $club, int $match, int $player): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        DB::transaction(function () use ($tenant, $match, $player, $request, $audit) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageRoster', $locked);
            $game = ClubMatch::where('club_id', $locked->id)->whereKey($match)->lockForUpdate()->firstOrFail();
            $record = DB::table('player_match_stats')->where('club_id', $locked->id)->where('match_id', $game->id)->where('player_id', $player);
            if (! $record->exists()) abort(404);
            $record->delete();
            $audit->record($locked, $request->user(), 'match.player_stats_removed', $game);
        });
        return response()->noContent();
    }
}
