<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_overwrite_skip_and_stale_decisions_are_safe(): void
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '5'])->assertCreated()->json('data.id');
        $person = ['full_name' => 'An', 'email' => 'an@example.test', 'status' => 'active'];
        $id = $this->postJson("/api/v1/clubs/$club/members", $person)->assertCreated()->json('data.id');
        $rows = [$person, ['full_name' => 'New', 'status' => 'active']];
        $preview = $this->postJson("/api/v1/clubs/$club/members/import/preview", ['players' => $rows])->assertOk()->assertJsonPath('data.0.matches.0.id', $id)->assertJsonCount(0, 'data.1.matches')->json('data');
        $decisions = [['action' => 'overwrite', 'target_id' => $id, 'match_versions' => [$preview[0]['matches'][0]['version']]], ['action' => 'create', 'match_versions' => []]];
        $this->postJson("/api/v1/clubs/$club/members/import/commit", ['players' => $rows, 'decisions' => $decisions])->assertCreated()->assertJsonPath('data.overwritten', 1)->assertJsonPath('data.created', 1);
        $this->assertDatabaseHas('player_profiles', ['id' => $id, 'full_name' => 'An']);
        $this->patchJson("/api/v1/clubs/$club/members/$id", ['full_name' => 'Changed'])->assertOk();
        $this->postJson("/api/v1/clubs/$club/members/import/commit", ['players' => [$person], 'decisions' => [$decisions[0]]])->assertUnprocessable();
        $this->assertDatabaseHas('player_profiles', ['id' => $id, 'full_name' => 'Changed']);
    }

    public function test_email_then_name_and_birth_date_and_unique_email(): void
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '5'])->assertCreated()->json('data.id');
        $id = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'email' => 'an@example.test', 'date_of_birth' => '2000-01-01'])->assertCreated()->json('data.id');
        $rows = [['full_name' => 'Khác', 'email' => 'an@example.test'], ['full_name' => 'An', 'date_of_birth' => '2000-01-01'], ['full_name' => 'An']];
        $preview = $this->postJson("/api/v1/clubs/$club/members/import/preview", ['players' => $rows])->assertOk()->assertJsonPath('data.0.matches.0.id', $id)->assertJsonPath('data.1.matches.0.id', $id)->assertJsonCount(0, 'data.2.matches')->json('data');
        $decisions = [['action' => 'create', 'match_versions' => [$preview[0]['matches'][0]['version']]], ['action' => 'skip', 'match_versions' => [$preview[1]['matches'][0]['version']]], ['action' => 'create', 'match_versions' => []]];
        $this->postJson("/api/v1/clubs/$club/members/import/commit", ['players' => $rows, 'decisions' => $decisions])->assertUnprocessable()->assertJsonValidationErrors('players.0.email');
        $this->assertDatabaseMissing('player_profiles', ['club_id' => $club, 'full_name' => 'Khác']);
        $decisions[0]['action'] = 'overwrite'; $decisions[0]['target_id'] = $id;
        $this->postJson("/api/v1/clubs/$club/members/import/commit", ['players' => $rows, 'decisions' => $decisions])->assertCreated()->assertJsonPath('data.overwritten', 1)->assertJsonPath('data.created', 1)->assertJsonPath('data.skipped', 1);
        $this->assertDatabaseHas('player_profiles', ['id' => $id, 'full_name' => 'Khác']);
    }
}
