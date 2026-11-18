<?php
namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClubDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_is_opt_in_and_invitations_require_recipient_decision(): void
    {
        CarbonImmutable::setTestNow('2026-05-01 10:00:00 Asia/Ho_Chi_Minh');
        try {
            $first = User::factory()->create(); $second = User::factory()->create(); $other = User::factory()->create();
            $this->actingAs($first);
            $a = $this->postJson('/api/v1/clubs', ['name' => 'Alpha', 'city' => 'HCM', 'description' => 'Private description', 'home_ground' => 'Private ground', 'format' => '7'])->assertCreated()->json('data.id');
            $this->actingAs($second);
            $b = $this->postJson('/api/v1/clubs', ['name' => 'Beta', 'city' => 'Hanoi', 'description' => 'Private B', 'home_ground' => 'Secret pitch', 'format' => '7'])->assertCreated()->json('data.id');
            $this->actingAs($first)->getJson('/api/v1/discover/clubs')->assertOk()->assertJsonCount(0, 'data');
            $payload = ['target_club_id' => $b, 'match_date' => '2026-05-12', 'match_time' => '18:00', 'location' => 'Sân A', 'message' => 'Mời giao hữu'];
            $this->postJson("/api/v1/clubs/$a/friendlies", $payload)->assertNotFound();
            $this->actingAs($second)->patchJson("/api/v1/clubs/$b", ['is_discoverable' => true])->assertOk();
            $result = $this->actingAs($first)->getJson('/api/v1/discover/clubs?q=Beta')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beta');
            $this->assertEqualsCanonicalizing(['id', 'name', 'city', 'formats', 'avatar_version'], array_keys($result->json('data.0')));
            $result->assertJsonMissing(['description' => 'Private B'])->assertJsonMissing(['home_ground' => 'Secret pitch']);
            $id = $this->postJson("/api/v1/clubs/$a/friendlies", $payload)->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
            $this->postJson("/api/v1/clubs/$a/friendlies", $payload)->assertUnprocessable();
            $this->patchJson("/api/v1/clubs/$a/friendlies/$id", ['status' => 'accepted'])->assertForbidden();
            $this->actingAs($other)->getJson("/api/v1/clubs/$a/friendlies")->assertNotFound();
            $this->actingAs($second)->getJson("/api/v1/clubs/$b/friendlies")->assertJsonPath('data.0.source_name', 'Alpha')->assertJsonPath('data.0.status', 'pending');
            $this->patchJson("/api/v1/clubs/$b/friendlies/$id", ['status' => 'accepted'])->assertNoContent();
            $this->patchJson("/api/v1/clubs/$b/friendlies/$id", ['status' => 'rejected'])->assertUnprocessable();
            $this->actingAs($first)->getJson("/api/v1/clubs/$a/friendlies")->assertJsonPath('data.0.status', 'accepted');
            $this->postJson("/api/v1/clubs/$a/friendlies", $payload)->assertCreated();
            $this->actingAs($second)->patchJson("/api/v1/clubs/$b", ['is_discoverable' => false])->assertOk();
            $this->actingAs($first)->getJson('/api/v1/discover/clubs')->assertJsonCount(0, 'data');
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_member_without_management_role_cannot_send_or_respond(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->getJson("/api/v1/clubs/$club/friendlies")->assertForbidden();
        $this->postJson("/api/v1/clubs/$club/friendlies", ['target_club_id' => 100, 'match_date' => '2099-01-01'])->assertForbidden();
    }

    public function test_discoverable_avatar_is_limited_to_opted_in_active_clubs(): void
    {
        Storage::fake('local'); $owner = User::factory()->create(); $visitor = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->post("/api/v1/clubs/$club/images/avatar", ['image' => UploadedFile::fake()->createWithContent('a.png', $png)], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($visitor)->get("/api/v1/discover/clubs/$club/avatar")->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/v1/clubs/$club", ['is_discoverable' => true])->assertOk();
        $this->actingAs($visitor)->get("/api/v1/discover/clubs/$club/avatar")->assertOk();
        $this->actingAs($owner)->patchJson("/api/v1/clubs/$club", ['is_discoverable' => false])->assertOk();
        $this->actingAs($visitor)->get("/api/v1/discover/clubs/$club/avatar")->assertNotFound();
    }
}
