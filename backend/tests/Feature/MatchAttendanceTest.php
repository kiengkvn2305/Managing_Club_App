<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_attendance_is_scoped_and_preserves_leavers(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Home', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $otherTeam = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Player A', 'team_id' => $team, 'email' => 'player@example.com'])->assertCreated()->json('data.id');
        $other = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Player B', 'team_id' => $otherTeam])->assertCreated()->json('data.id');
        $game = ['team_id' => $team, 'opponent' => 'Visitors', 'match_date' => '2026-12-01', 'start_time' => '18:00', 'match_type' => 'friendly', 'status' => 'scheduled'];
        $match = $this->postJson("/api/v1/clubs/$club/matches", $game)->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$club/matches/$match/attendance";
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.rsvp', 'pending');
        $record = ['player_id' => $player, 'rsvp' => 'yes', 'attendance' => 'present'];
        $this->putJson($url, array_replace($record, ['player_id' => $other]))->assertUnprocessable();
        $this->putJson($url, array_replace($record, ['attendance' => 'wrong']))->assertUnprocessable();
        $this->putJson($url, $record)->assertOk();
        $this->putJson($url, array_replace($record, ['attendance' => 'late']))->assertOk();
        $this->assertSame(1, DB::table('match_attendances')->where('match_id', $match)->count());
        $this->patchJson("/api/v1/clubs/$club/members/$player", ['team_id' => $otherTeam])->assertOk();
        $this->getJson($url)->assertJsonCount(1, 'data')->assertJsonPath('data.0.current_member', false)->assertJsonPath('data.0.attendance', 'late');
        $this->putJson($url, array_replace($record, ['attendance' => 'excused']))->assertOk();
        $this->patchJson("/api/v1/clubs/$club/matches/$match", array_replace($game, ['status' => 'cancelled']))->assertOk();
        $this->putJson($url, $record)->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('data.0.attendance', 'excused');
        $member = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->getJson($url)->assertForbidden();
        $this->actingAs($member)->putJson($url, $record)->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
    }

    public function test_match_from_another_club_cannot_be_accessed(): void
    {
        $user = User::factory()->create(); $this->actingAs($user);
        $a = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$b/teams", ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$b/members", ['full_name' => 'B', 'team_id' => $team])->assertCreated()->json('data.id');
        $match = $this->postJson("/api/v1/clubs/$b/matches", ['team_id' => $team, 'opponent' => 'A', 'match_date' => '2026-12-01', 'start_time' => '18:00', 'match_type' => 'friendly', 'status' => 'scheduled'])->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$a/matches/$match/attendance";
        $this->getJson($url)->assertNotFound();
        $this->putJson($url, ['player_id' => $player, 'rsvp' => 'yes', 'attendance' => 'present'])->assertNotFound();
        try {
            DB::table('match_attendances')->insert(['club_id' => $a, 'match_id' => $match, 'player_id' => $player, 'rsvp' => 'yes', 'attendance' => 'present']);
            $this->fail('Cross-club record must violate the foreign key.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY', strtoupper($exception->getMessage()));
        }
    }
}
