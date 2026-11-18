<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_uses_club_player_profiles_without_creating_accounts(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '5'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'status' => 'active'])->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$club/board-members";
        $id = $this->postJson($url, ['player_id' => $player, 'title' => 'Thủ quỹ'])->assertCreated()->json('data.id');
        $this->postJson($url, ['player_id' => $player, 'title' => 'Thư ký'])->assertUnprocessable();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.full_name', 'An')->assertJsonPath('data.0.title', 'Thủ quỹ');
        $this->patchJson("$url/$id", ['title' => 'Thư ký'])->assertNoContent();
        $this->assertDatabaseCount('users', 1);
        $other = User::factory()->create();
        $otherClub = $this->actingAs($other)->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '5'])->assertCreated()->json('data.id');
        $foreignPlayer = $this->postJson("/api/v1/clubs/$otherClub/members", ['full_name' => 'Binh', 'status' => 'active'])->assertCreated()->json('data.id');
        $this->actingAs($owner)->postJson($url, ['player_id' => $foreignPlayer, 'title' => 'Thủ quỹ'])->assertNotFound();
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $task = DB::table('board_tasks')->insertGetId(['club_id' => $club, 'board_member_id' => $id, 'title' => 'Công việc cũ', 'progress' => 25, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->getJson("/api/v1/clubs/$club/board-tasks")->assertNotFound();
        $this->deleteJson("/api/v1/clubs/$club/members/$player")->assertUnprocessable();
        $this->deleteJson("$url/$id")->assertNoContent();
        $this->assertDatabaseHas('board_tasks', ['id' => $task]);
        $this->getJson($url)->assertJsonCount(0, 'data');
        $this->postJson($url, ['player_id' => $player, 'title' => 'Thư ký'])->assertCreated()->assertJsonPath('data.id', $id);
        $this->deleteJson("$url/$id")->assertNoContent();
        $this->assertDatabaseHas('player_profiles', ['id' => $player]);
    }
}
