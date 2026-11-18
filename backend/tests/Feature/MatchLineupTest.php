<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchLineupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lineups_validate_team_players_capacity_and_tenant_access(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Home', 'formats' => ['5', '7']])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Five', 'format' => '5'])->assertCreated()->json('data.id');
        $otherTeam = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Other', 'format' => '7'])->assertCreated()->json('data.id');
        $players = [];
        for ($i = 0; $i < 6; $i++) {
            $players[] = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => "Player $i", 'team_id' => $team])->assertCreated()->json('data.id');
        }
        $outsider = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Other player', 'team_id' => $otherTeam])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/clubs/$club/teams/$team/captain", ['captain_id' => $outsider])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club/teams/$team/captain", ['captain_id' => $players[0]])->assertOk();
        $entries = array_map(fn ($id) => ['player_id' => $id, 'role' => 'starter', 'x' => 50, 'y' => 50], $players);
        $payload = ['team_id' => $team, 'opponent' => 'Visitors', 'match_date' => '2026-12-01', 'formation' => '1-2-1', 'players' => $entries];
        $this->postJson("/api/v1/clubs/$club/lineups", $payload)->assertUnprocessable()->assertJsonValidationErrors('players');
        $payload['players'][5]['role'] = 'substitute';
        $id = $this->postJson("/api/v1/clubs/$club/lineups", $payload)->assertCreated()->json('data.id');
        $this->getJson("/api/v1/clubs/$club/lineups")->assertOk()->assertJsonPath('data.0.players.5.role', 'substitute');
        $payload['players'][0]['player_id'] = $outsider;
        $this->patchJson("/api/v1/clubs/$club/lineups/$id", $payload)->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson("/api/v1/clubs/$club/lineups")->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/v1/clubs/$club/members/{$players[0]}", ['team_id' => $otherTeam])->assertOk();
        $this->assertNull(DB::table('teams')->where('id', $team)->value('captain_id'));
    }
}
