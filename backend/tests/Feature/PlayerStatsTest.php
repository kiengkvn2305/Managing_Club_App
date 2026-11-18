<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_are_scoped_to_completed_games_and_aggregate_real_records(): void
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Home', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $otherTeam = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Player A', 'team_id' => $team])->assertCreated()->json('data.id');
        $other = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Player B', 'team_id' => $otherTeam])->assertCreated()->json('data.id');
        $game = ['team_id' => $team, 'opponent' => 'Visitors', 'match_date' => '2026-12-01', 'start_time' => '18:00', 'match_type' => 'friendly', 'status' => 'scheduled'];
        $match = $this->postJson("/api/v1/clubs/$club/matches", $game)->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$club/matches/$match/player-stats";
        $data = ['player_id' => $player, 'goals' => 2, 'assists' => 1, 'yellow_cards' => 1, 'red_cards' => 0, 'goals_conceded' => 0, 'rating' => 8.5];
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.stat_id', null);
        $this->putJson($url, $data)->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/matches/$match", array_replace($game, ['status' => 'completed', 'our_score' => 2, 'opponent_score' => 0]))->assertOk();
        $this->putJson($url, array_replace($data, ['player_id' => $other]))->assertUnprocessable();
        $this->putJson($url, array_replace($data, ['goals' => 3]))->assertUnprocessable();
        $this->putJson($url, array_replace($data, ['goals_conceded' => 1]))->assertUnprocessable();
        $this->putJson($url, $data)->assertOk();
        $this->putJson($url, $data)->assertOk();
        $this->assertSame(1, DB::table('player_match_stats')->count());
        $summary = "/api/v1/clubs/$club/player-stats?from=2026-01-01&to=2026-12-31";
        $this->getJson($summary)->assertOk()->assertJsonPath('data.players.0.matches_played', 1)->assertJsonPath('data.players.0.goals', 2)->assertJsonPath('data.players.0.clean_sheets', 1)->assertJsonPath('data.players.0.average_rating', 8.5)->assertJsonPath('data.club.matches', 1);
        $this->getJson("$summary&team_id=$otherTeam")->assertJsonCount(0, 'data.players');
        $this->getJson("/api/v1/clubs/$club/player-stats?from=2025-01-01&to=2025-12-31")->assertJsonCount(0, 'data.players');
        $this->patchJson("/api/v1/clubs/$club/members/$player", ['team_id' => $otherTeam])->assertOk();
        $this->getJson($url)->assertJsonCount(1, 'data')->assertJsonPath('data.0.stat_id', 1);
        $this->putJson($url, array_replace($data, ['goals_conceded' => null]))->assertOk();
        $this->getJson($summary)->assertJsonPath('data.players.0.goalkeeper_matches', 0)->assertJsonPath('data.players.0.clean_sheets', 0);
        $this->deleteJson("$url/$player")->assertNoContent();
        $this->getJson($summary)->assertJsonCount(0, 'data.players');
        $this->deleteJson("$url/$player")->assertNotFound();
    }

    public function test_stats_reject_cross_tenant_data_and_unauthorized_users(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$b/teams", ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$b/members", ['full_name' => 'B', 'team_id' => $team])->assertCreated()->json('data.id');
        $match = $this->postJson("/api/v1/clubs/$b/matches", ['team_id' => $team, 'opponent' => 'A', 'match_date' => '2026-12-01', 'start_time' => '18:00', 'match_type' => 'friendly', 'status' => 'completed', 'our_score' => 1, 'opponent_score' => 0])->assertCreated()->json('data.id');
        $data = ['player_id' => $player, 'goals' => 1, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0];
        $this->getJson("/api/v1/clubs/$a/matches/$match/player-stats")->assertNotFound();
        $this->putJson("/api/v1/clubs/$a/matches/$match/player-stats", $data)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson("/api/v1/clubs/$b/player-stats?from=2026-01-01&to=2026-12-31")->assertNotFound();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('player_match_stats')->insert(['club_id' => $a, 'match_id' => $match, 'player_id' => $player]);
    }
}
