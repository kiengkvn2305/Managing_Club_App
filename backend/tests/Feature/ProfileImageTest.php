<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_club_player_and_account_images_are_private_and_replaceable(): void
    {
        Storage::fake('local'); $disk = Storage::disk('local');
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $base = "/api/v1/clubs/$club";
        $player = $this->postJson("$base/members", ['full_name' => 'An'])->assertCreated()->json('data.id');
        $this->post("$base/images/avatar", ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['data' => ['version']]);
        $old = DB::table('clubs')->where('id', $club)->value('avatar_path'); $disk->assertExists($old);
        $this->post("$base/images/avatar", ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $disk->assertMissing($old); $new = DB::table('clubs')->where('id', $club)->value('avatar_path'); $disk->assertExists($new);
        $this->post("$base/images/background", ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->getJson('/api/v1/clubs')->assertJsonPath('data.0.avatar_version', hash('sha256', $new))->assertJsonMissingPath('data.0.avatar_path')->assertJsonMissingPath('data.0.background_path');
        $this->post("$base/members/$player/avatar", ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->getJson("$base/members/$player")->assertJsonPath('data.has_custom_avatar', true)->assertJsonMissingPath('data.avatar_path');
        $this->get("$base/members/$player/avatar")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/api/v1/auth/avatar', ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->getJson('/api/v1/auth/user')->assertJsonMissingPath('data.avatar_path')->assertJsonStructure(['data' => ['avatar_version']]);
        $this->post("$base/images/avatar", ['image' => UploadedFile::fake()->createWithContent('file.txt', 'not an image')], ['Accept' => 'application/json'])->assertUnprocessable();
        $member = User::factory()->create(); DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->get("$base/images/avatar")->assertOk();
        $this->post("$base/images/avatar", ['image' => $this->image()], ['Accept' => 'application/json'])->assertForbidden();
        $this->get("$base/members/$player/avatar")->assertForbidden();
        DB::table('player_profiles')->where('id', $player)->update(['user_id' => $member->id]);
        $this->getJson("$base/my-player")->assertOk()->assertJsonPath('data.id', $player);
        $this->post("$base/members/$player/avatar", ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs(User::factory()->create())->get("$base/images/avatar")->assertNotFound();
        $this->actingAs($owner)->deleteJson("$base/images/avatar")->assertOk()->assertJsonPath('data.version', null);
        $this->getJson("$base/my-player")->assertOk()->assertJsonPath('data', null);
        $disk->assertMissing($new); $this->get("$base/images/avatar")->assertNotFound();
    }

    public function test_linked_account_image_is_used_when_player_has_no_custom_image(): void
    {
        Storage::fake('local'); $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An'])->assertCreated()->json('data.id');
        DB::table('player_profiles')->where('id', $player)->update(['user_id' => $owner->id]);
        $this->post('/api/v1/auth/avatar', ['image' => $this->image()], ['Accept' => 'application/json'])->assertOk();
        $version = $owner->fresh()->avatar_version;
        $this->getJson("/api/v1/clubs/$club/members/$player")->assertJsonPath('data.avatar_version', $version)->assertJsonPath('data.has_custom_avatar', false);
        $this->get("/api/v1/clubs/$club/members/$player/avatar")->assertOk();
    }
}
