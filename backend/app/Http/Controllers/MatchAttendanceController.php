<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMatch;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchAttendanceController extends Controller
{
    public function index(Request $request, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('viewRoster', $tenant);
        $game = ClubMatch::where('club_id', $tenant->id)->findOrFail($match);

        // Include saved players who have since left the team; their match record is historical.
        $players = DB::table('player_profiles as p')
            ->leftJoin('team_players as tp', function ($join) use ($tenant, $game) {
                $join->on('tp.player_id', '=', 'p.id')->where('tp.club_id', $tenant->id)->where('tp.team_id', $game->team_id);
            })->leftJoin('match_attendances as a', function ($join) use ($tenant, $game) {
                $join->on('a.player_id', '=', 'p.id')->where('a.club_id', $tenant->id)->where('a.match_id', $game->id);
            })->where('p.club_id', $tenant->id)
            ->where(fn ($query) => $query->whereNotNull('tp.player_id')->orWhereNotNull('a.id'))
            ->select('p.id as player_id', 'p.full_name', 'p.date_of_birth', 'p.email', 'tp.position', 'tp.jersey_number', 'tp.player_id as current_team_player', 'a.rsvp', 'a.attendance')
            ->orderBy('p.full_name')->orderBy('p.id')->get()->map(fn ($row) => [
                'player_id' => $row->player_id, 'full_name' => $row->full_name, 'date_of_birth' => $row->date_of_birth, 'email' => $row->email,
                'position' => $row->position, 'jersey_number' => $row->jersey_number, 'current_member' => $row->current_team_player !== null,
                'rsvp' => $row->rsvp ?? 'pending', 'attendance' => $row->attendance ?? 'unmarked',
            ]);
        return response()->json(['data' => $players]);
    }

    public function save(Request $request, AuditService $audit, string $club, int $match): mixed
    {
        $tenant = $request->attributes->get('club');
        Gate::authorize('manageRoster', $tenant);
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1'],
            'rsvp' => ['required', Rule::in(['pending', 'yes', 'no', 'maybe'])],
            'attendance' => ['required', Rule::in(['unmarked', 'present', 'absent', 'late', 'excused'])],
        ]);
        DB::transaction(function () use ($tenant, $match, $data, $request, $audit) {
            $locked = Club::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageRoster', $locked);
            $game = ClubMatch::where('club_id', $locked->id)->whereKey($match)->lockForUpdate()->firstOrFail();
            if ($game->status === 'cancelled') throw ValidationException::withMessages(['attendance' => ['Không thể điểm danh trận đã hủy.']]);
            $existing = DB::table('match_attendances')->where('club_id', $locked->id)->where('match_id', $game->id)->where('player_id', $data['player_id'])->exists();
            $current = DB::table('team_players')->where('club_id', $locked->id)->where('team_id', $game->team_id)->where('player_id', $data['player_id'])->exists();
            $belongs = DB::table('player_profiles')->where('club_id', $locked->id)->where('id', $data['player_id'])->exists();
            if (! $belongs || (! $current && ! $existing)) throw ValidationException::withMessages(['player_id' => ['Thành viên không thuộc đội của trận đấu.']]);
            DB::table('match_attendances')->updateOrInsert(
                ['match_id' => $game->id, 'player_id' => $data['player_id']],
                ['club_id' => $locked->id, 'rsvp' => $data['rsvp'], 'attendance' => $data['attendance'], 'updated_at' => now(), ...($existing ? [] : ['created_at' => now()])]
            );
            $audit->record($locked, $request->user(), 'match.attendance_updated', $game);
        });
        return response()->json(['data' => ['saved' => true]]);
    }
}
