<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchCostTest extends TestCase
{
    use RefreshDatabase;

    private function setupGame(): array
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $players = [];
        foreach (['One', 'Two', 'Three'] as $name) $players[] = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => $name, 'team_id' => $team])->assertCreated()->json('data.id');
        $match = $this->postJson("/api/v1/clubs/$club/matches", ['team_id' => $team, 'opponent' => 'B', 'match_date' => '2026-12-01', 'start_time' => '18:00', 'match_type' => 'friendly', 'status' => 'scheduled'])->assertCreated()->json('data.id');
        return [$club, $team, $players, $match];
    }

    private function payload(array $players): array
    {
        return ['stadium' => 100, 'referee' => 5, 'water' => 0, 'equipment' => 0, 'other' => 0, 'sponsorship' => 4, 'method' => 'equal', 'players' => array_map(fn ($id) => ['player_id' => $id, 'exempt' => false], $players)];
    }

    public function test_equal_split_preview_confirmation_is_exact_atomic_and_idempotent(): void
    {
        [$club, $team, $players, $match] = $this->setupGame();
        $url = "/api/v1/clubs/$club/matches/$match/cost-split"; $body = $this->payload($players);
        $this->postJson("$url/preview", $body)->assertOk()->assertJsonPath('data.total', 101)->assertJsonPath('data.receivables.0.amount', 34)->assertJsonPath('data.receivables.1.amount', 34)->assertJsonPath('data.receivables.2.amount', 33);
        $this->assertDatabaseCount('match_cost_splits', 0);
        $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.replayed', false);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.replayed', true);
        $this->postJson($url, array_replace($body, ['stadium' => 99]))->assertStatus(409);
        $this->assertDatabaseCount('match_cost_splits', 1);
        $this->assertSame(101, (int) DB::table('match_cost_receivables')->sum('amount'));
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->getJson($url)->assertOk()->assertJsonCount(3, 'data.receivables');
    }

    public function test_custom_and_weighted_exemptions_and_invalid_allocations(): void
    {
        [$club, $team, $players, $match] = $this->setupGame(); $url = "/api/v1/clubs/$club/matches/$match/cost-split";
        $body = $this->payload($players); $body['method'] = 'weighted';
        $body['players'] = [['player_id' => $players[0], 'exempt' => false, 'weight' => 1], ['player_id' => $players[1], 'exempt' => false, 'weight' => 2], ['player_id' => $players[2], 'exempt' => true]];
        $this->postJson("$url/preview", $body)->assertOk()->assertJsonPath('data.receivables.0.amount', 34)->assertJsonPath('data.receivables.1.amount', 67)->assertJsonPath('data.receivables.2.amount', 0);
        $body['method'] = 'custom'; $body['players'][0]['amount'] = 40; $body['players'][1]['amount'] = 60;
        $this->postJson($url, $body)->assertUnprocessable(); $this->assertDatabaseCount('match_cost_splits', 0);
        $body['players'][1]['amount'] = 61; $this->postJson($url, $body)->assertCreated();
        $this->assertSame(101, (int) DB::table('match_cost_receivables')->sum('amount'));
    }

    public function test_scope_and_permissions_and_invalid_players(): void
    {
        [$club, $team, $players, $match] = $this->setupGame(); $url = "/api/v1/clubs/$club/matches/$match/cost-split";
        $otherClub = $this->postJson('/api/v1/clubs', ['name' => 'Other', 'format' => '7'])->assertCreated()->json('data.id');
        $otherTeam = $this->postJson("/api/v1/clubs/$otherClub/teams", ['name' => 'Other', 'format' => '7'])->assertCreated()->json('data.id');
        $other = $this->postJson("/api/v1/clubs/$otherClub/members", ['full_name' => 'Other', 'team_id' => $otherTeam])->assertCreated()->json('data.id');
        $this->postJson("$url/preview", $this->payload([$players[0], $other]))->assertUnprocessable();
        $this->postJson("$url/preview", $this->payload([$players[0], $players[0]]))->assertUnprocessable();
        $this->getJson("/api/v1/clubs/$otherClub/matches/$match/cost-split")->assertNotFound();
        $member = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->getJson($url)->assertForbidden();
        $this->postJson($url, $this->payload($players))->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
    }
}
