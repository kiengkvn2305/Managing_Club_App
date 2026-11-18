<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LineupController extends Controller
{
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
            $ids = array_column($data['players'], 'player_id');
            if (count($ids) !== DB::table('team_players')->where('club_id', $tenant->id)->where('team_id', $team->id)->whereIn('player_id', $ids)->count()) {
                throw ValidationException::withMessages(['players' => ['Chỉ được chọn cầu thủ thuộc đội bóng này.']]);
            }
            if (count(array_filter($data['players'], fn ($p) => $p['role'] === 'starter')) > (int) $team->format) {
                throw ValidationException::withMessages(['players' => ['Số cầu thủ đá chính vượt quá loại sân.']]);
            }
            $values = ['club_id' => $tenant->id, 'team_id' => $team->id, 'opponent' => $data['opponent'], 'match_date' => $data['match_date'], 'formation' => $data['formation'] ?? null, 'players' => json_encode($data['players']), 'updated_at' => now()];
            if ($lineup !== null) {
                $existing = DB::table('match_lineups')->where('club_id', $tenant->id)->where('id', $lineup)->first();
                abort_unless($existing, 404);
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
