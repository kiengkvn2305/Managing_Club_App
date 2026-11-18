<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerSelfLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_member_can_link_only_their_matching_profile_and_unlink(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(['email' => 'an@example.test']); $other = User::factory()->create();
        $this->actingAs($owner); $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'email' => 'an@example.test'])->assertCreated()->json('data.id');
        foreach ([$member, $other] as $user) DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->postJson("/api/v1/clubs/$club/my-player/claim")->assertUnprocessable();
        $this->actingAs($member)->getJson("/api/v1/clubs/$club/my-player")->assertJsonPath('eligible', true);
        $this->postJson("/api/v1/clubs/$club/my-player/claim")->assertNoContent();
        $this->assertDatabaseHas('player_profiles', ['id' => $player, 'club_id' => $club, 'user_id' => $member->id]);
        $this->getJson("/api/v1/clubs/$club/my-player")->assertJsonPath('data.id', $player)->assertJsonPath('eligible', false);
        $this->postJson("/api/v1/clubs/$club/my-player/claim")->assertUnprocessable();
        $this->deleteJson("/api/v1/clubs/$club/my-player/claim")->assertNoContent();
        $this->assertDatabaseHas('player_profiles', ['id' => $player, 'user_id' => null]);
        $this->actingAs(User::factory()->create())->postJson("/api/v1/clubs/$club/my-player/claim")->assertNotFound();
    }

    public function test_unverified_email_cannot_claim_profile(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->unverified()->create(['email' => 'an@example.test']);
        $this->actingAs($owner); $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'email' => 'an@example.test'])->assertCreated();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->getJson("/api/v1/clubs/$club/my-player")->assertJsonPath('eligible', false);
        $this->postJson("/api/v1/clubs/$club/my-player/claim")->assertUnprocessable();
    }
}
