<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $club, User $user, string $status = 'active'): void
    {
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => 'member', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_poll_snapshots_active_electors_enforces_deadline_and_privacy(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $late = User::factory()->create();
        $this->actingAs($owner); $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $this->member($club, $member); $base = "/api/v1/clubs/$club/polls";
        $payload = ['title' => 'Chọn sân', 'deadline' => now()->addHour()->toISOString(), 'options' => ['Sân A', 'Sân B']];
        $poll = $this->postJson($base, $payload)->assertCreated()->assertJsonPath('data.eligible_count', 2)->json('data');
        $this->member($club, $late);
        $this->actingAs($late)->postJson("$base/{$poll['id']}/vote", ['option_id' => $poll['options'][0]['id']])->assertForbidden();
        $this->actingAs($member)->postJson($base, $payload)->assertForbidden();
        $this->postJson("$base/{$poll['id']}/vote", ['option_id' => $poll['options'][0]['id']])->assertCreated()->assertJsonPath('data.voted', true);
        $this->postJson("$base/{$poll['id']}/vote", ['option_id' => $poll['options'][1]['id']])->assertUnprocessable();
        $json = $this->actingAs($owner)->getJson($base)->assertOk()->assertJsonPath('data.0.options.0.votes', 1)->assertJsonPath('data.0.voted', false)->json();
        $this->assertStringNotContainsString((string) $member->email, json_encode($json));
        $this->assertArrayNotHasKey('user_id', $json['data'][0]);
        $this->travel(61)->minutes();
        $this->postJson("$base/{$poll['id']}/vote", ['option_id' => $poll['options'][1]['id']])->assertUnprocessable();
    }

    public function test_inactive_and_other_club_members_cannot_vote(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $otherOwner = User::factory()->create();
        $this->actingAs($owner); $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $this->member($club, $member); $base = "/api/v1/clubs/$club/polls";
        $poll = $this->postJson($base, ['title' => 'Poll', 'deadline' => now()->addHour()->toISOString(), 'options' => ['A', 'B']])->assertCreated()->json('data');
        DB::table('club_members')->where('club_id', $club)->where('user_id', $member->id)->update(['status' => 'inactive']);
        $this->actingAs($member)->postJson("$base/{$poll['id']}/vote", ['option_id' => $poll['options'][0]['id']])->assertNotFound();
        $this->actingAs($otherOwner); $other = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/clubs/$other/polls/{$poll['id']}/vote", ['option_id' => $poll['options'][0]['id']])->assertNotFound();
        $this->getJson($base)->assertNotFound();
    }

    public function test_unique_database_constraint_blocks_competing_duplicate_votes(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $poll = $this->postJson("/api/v1/clubs/$club/polls", ['title' => 'Poll', 'deadline' => now()->addHour()->toISOString(), 'options' => ['A', 'B']])->assertCreated()->json('data');
        $row = ['club_id' => $club, 'poll_id' => $poll['id'], 'user_id' => $owner->id, 'option_id' => $poll['options'][0]['id'], 'created_at' => now(), 'updated_at' => now()];
        DB::table('club_poll_votes')->insert($row);
        $this->expectException(QueryException::class); DB::table('club_poll_votes')->insert($row);
    }
}
