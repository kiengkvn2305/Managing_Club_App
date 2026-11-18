<?php
namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_are_scoped_validated_and_can_reference_an_accepted_invitation(): void
    {
        CarbonImmutable::setTestNow('2026-05-01 10:00:00 Asia/Ho_Chi_Minh');
        try {
            $a = User::factory()->create(); $b = User::factory()->create(); $outsider = User::factory()->create();
            $this->actingAs($a); $clubA = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
            $team = $this->postJson("/api/v1/clubs/$clubA/teams", ['name' => 'Team A', 'format' => '7'])->assertCreated()->json('data.id');
            $this->actingAs($b); $clubB = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7', 'is_discoverable' => true])->assertCreated()->json('data.id');
            $foreignTeam = $this->postJson("/api/v1/clubs/$clubB/teams", ['name' => 'Team B', 'format' => '7'])->assertCreated()->json('data.id');
            $payload = ['team_id' => $team, 'opponent' => 'B', 'match_date' => '2026-05-12', 'start_time' => '18:00', 'end_time' => '19:00', 'match_type' => 'friendly', 'status' => 'scheduled'];
            $this->actingAs($a); $invite = $this->postJson("/api/v1/clubs/$clubA/friendlies", ['target_club_id' => $clubB, 'match_date' => '2026-05-12'])->assertCreated()->json('data.id');
            $withInvite = $payload + ['friendly_invitation_id' => $invite];
            $this->postJson("/api/v1/clubs/$clubA/matches", $withInvite)->assertUnprocessable();
            $this->actingAs($b)->patchJson("/api/v1/clubs/$clubB/friendlies/$invite", ['status' => 'accepted'])->assertNoContent();
            $this->actingAs($a); $id = $this->postJson("/api/v1/clubs/$clubA/matches", $withInvite)->assertCreated()->json('data.id');
            $lineupData = ['team_id' => $team, 'club_match_id' => $id, 'opponent' => 'B', 'match_date' => '2026-05-12', 'players' => []];
            $lineup = $this->postJson("/api/v1/clubs/$clubA/lineups", $lineupData)->assertCreated()->json('data.id');
            $this->postJson("/api/v1/clubs/$clubA/lineups", $lineupData)->assertUnprocessable();
            $this->getJson("/api/v1/clubs/$clubA/matches/$id/lineup")->assertOk()->assertJsonPath('data.id', $lineup);
            $this->postJson("/api/v1/clubs/$clubA/matches", $withInvite)->assertUnprocessable();
            $this->getJson("/api/v1/clubs/$clubA/matches?month=2026-05")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.team_name', 'Team A');
            $this->getJson("/api/v1/clubs/$clubA/matches?month=2026-06")->assertJsonCount(0, 'data');
            $this->getJson("/api/v1/clubs/$clubA/matches/calendar?from=2026-05-11&to=2026-05-17")->assertOk()->assertJsonCount(1, 'data');
            $this->getJson("/api/v1/clubs/$clubA/matches/calendar?from=2026-01-01&to=2026-03-01")->assertUnprocessable();
            $this->patchJson("/api/v1/clubs/$clubA/matches/$id", array_replace($withInvite, ['status' => 'completed']))->assertUnprocessable();
            $this->patchJson("/api/v1/clubs/$clubA/matches/$id", array_replace($withInvite, ['team_id' => $foreignTeam]))->assertUnprocessable();
            $this->patchJson("/api/v1/clubs/$clubA/matches/$id", array_replace($withInvite, ['end_time' => '17:00']))->assertUnprocessable();
            $this->patchJson("/api/v1/clubs/$clubA/matches/$id", array_replace($withInvite, ['status' => 'completed', 'our_score' => 2, 'opponent_score' => 1]))->assertOk();
            $this->patchJson("/api/v1/clubs/$clubA/matches/$id", array_replace($withInvite, ['status' => 'completed', 'our_score' => 2, 'opponent_score' => 1, 'opponent' => 'B updated']))->assertOk();
            $this->assertDatabaseHas('match_lineups', ['id' => $lineup, 'club_match_id' => $id, 'opponent' => 'B updated']);
            $this->patchJson("/api/v1/clubs/$clubA/lineups/$lineup", ['team_id' => $team, 'opponent' => 'ignored', 'match_date' => '2026-05-12', 'players' => []])->assertOk();
            $this->assertDatabaseHas('match_lineups', ['id' => $lineup, 'club_match_id' => $id, 'opponent' => 'B updated']);
            $this->getJson("/api/v1/clubs/$clubA/matches?month=2026-05")->assertJsonPath('data.0.our_score', 2);
            $member = User::factory()->create(); DB::table('club_members')->insert(['club_id' => $clubA, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($member)->getJson("/api/v1/clubs/$clubA/matches?month=2026-05")->assertForbidden();
            $this->actingAs($outsider)->getJson("/api/v1/clubs/$clubA/matches?month=2026-05")->assertNotFound();
            $this->actingAs($a)->deleteJson("/api/v1/clubs/$clubA/teams/$team")->assertNoContent();
            $this->assertDatabaseHas('club_matches', ['id' => $id, 'club_id' => $clubA, 'team_id' => null, 'opponent' => 'B updated']);
        } finally { CarbonImmutable::setTestNow(); }
    }
}
