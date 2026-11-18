<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LineupController extends Controller
{
    public function forMatch(Request $request, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club'); Gate::authorize('viewRoster', $tenant);
        $game = DB::table('club_matches')->where('club_id', $tenant->id)->where('id', $match)->first(); abort_unless($game, 404);
        $lineup = DB::table('match_lineups')->where('club_id', $tenant->id)->where('club_match_id', $match)->first();
        if ($lineup) $lineup->players = json_decode($lineup->players, true);
        return response()->json(['data' => $lineup]);
    }
    public function index(Request $request): mixed
    {
        $club = $request->attributes->get('club');
        Gate::authorize('viewRoster', $club);
        return response()->json(['data' => DB::table('match_lineups')->where('club_id', $club->id)->orderByDesc('match_date')->orderByDesc('id')->get()->map(function ($row) {
            $row->players = json_decode($row->players, true);
            return $row;
        })]);
    }

    public function save(Request $request, string $club, ?int $lineup = null): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('club_id', $tenant->id)],
            'club_match_id' => ['nullable', 'integer', 'min:1'],
            'opponent' => ['required', 'string', 'max:120'],
            'match_date' => ['required', 'date_format:Y-m-d'],
            'formation' => ['nullable', 'string', 'max:120'],
            'players' => ['present', 'array', 'max:500'],
            'players.*.player_id' => ['required', 'integer', 'distinct'],
            'players.*.role' => ['required', Rule::in(['starter', 'substitute'])],
            'players.*.x' => ['required', 'numeric', 'between:0,100'],
            'players.*.y' => ['required', 'numeric', 'between:0,100'],
        ]);
        return DB::transaction(function () use ($request, $tenant, $data, $lineup) {
            // Serialize changes to a club's roster and lineup to avoid cross-team races.
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $team = DB::table('teams')->where('club_id', $tenant->id)->where('id', $data['team_id'])->first();
            $existing = $lineup !== null ? DB::table('match_lineups')->where('club_id', $tenant->id)->where('id', $lineup)->first() : null;
            if ($lineup !== null) abort_unless($existing, 404);
            $matchId = array_key_exists('club_match_id', $data) ? $data['club_match_id'] : ($existing->club_match_id ?? null);
            if ($matchId) {
                $game = DB::table('club_matches')->where('club_id', $tenant->id)->where('id', $matchId)->first();
                if (! $game || (int) $game->team_id !== (int) $team->id) throw ValidationException::withMessages(['club_match_id' => ['Trận đấu phải thuộc CLB và cùng đội bóng.']]);
                if (DB::table('match_lineups')->where('club_id', $tenant->id)->where('club_match_id', $matchId)->when($lineup !== null, fn ($q) => $q->where('id', '!=', $lineup))->exists()) throw ValidationException::withMessages(['club_match_id' => ['Trận đấu đã có đội hình.']]);
                $data['opponent'] = $game->opponent; $data['match_date'] = $game->match_date;
            }
            $ids = array_column($data['players'], 'player_id');
            if (count($ids) !== DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team->id)->whereIn('player_id', $ids)->count()) {
                throw ValidationException::withMessages(['players' => ['Chỉ được chọn cầu thủ thuộc đội bóng này.']]);
            }
            if (count(array_filter($data['players'], fn ($p) => $p['role'] === 'starter')) > (int) $team->format) {
                throw ValidationException::withMessages(['players' => ['Số cầu thủ đá chính vượt quá loại sân.']]);
            }
            $values = ['club_id' => $tenant->id, 'team_id' => $team->id, 'club_match_id' => $matchId, 'opponent' => $data['opponent'], 'match_date' => $data['match_date'], 'formation' => $data['formation'] ?? null, 'players' => json_encode($data['players']), 'updated_at' => now()];
            if ($lineup !== null) {
                DB::table('match_lineups')->where('id', $lineup)->update($values);
                $id = $lineup;
            } else {
                $id = DB::table('match_lineups')->insertGetId($values + ['created_at' => now()]);
            }
            return response()->json(['data' => ['id' => $id]], $lineup === null ? 201 : 200);
        });
    }

    public function captain(Request $request, string $club, int $team): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate(['captain_id' => ['present', 'nullable', 'integer']]);
        return DB::transaction(function () use ($tenant, $team, $data) {
            DB::table('clubs')->where('id', $tenant->id)->lockForUpdate()->first();
            $target = DB::table('teams')->where('club_id', $tenant->id)->where('id', $team)->first();
            abort_unless($target, 404);
            if ($data['captain_id'] !== null && ! DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team)->where('player_id', $data['captain_id'])->exists()) {
                throw ValidationException::withMessages(['captain_id' => ['Đội trưởng phải thuộc đội bóng.']]);
            }
            DB::table('teams')->where('id', $team)->update(['captain_id' => $data['captain_id']]);
            return response()->json(['data' => ['captain_id' => $data['captain_id']]]);
        });
    }
}
