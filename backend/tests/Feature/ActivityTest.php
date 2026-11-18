<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_attendance_and_historical_snapshots(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '5'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Đội', 'format' => '5'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'team_id' => $team, 'date_of_birth' => '2000-03-10'])->assertCreated()->json('data.id');
        $other = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Other'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/clubs/$club/activities/celebrations", ['title' => 'Tết', 'event_date' => '2027-02-06', 'message' => 'Chúc mừng'])->assertCreated();
        $training = $this->postJson("/api/v1/clubs/$club/activities/trainings", ['team_id' => $team, 'training_date' => '2027-02-01', 'training_time' => '18:30', 'location' => 'Sân'])->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$club/activities/trainings/$training/attendance";
        $this->patchJson($url, ['players' => [['player_id' => $other, 'status' => 'present']]])->assertUnprocessable();
        $this->patchJson($url, ['players' => [['player_id' => $player, 'status' => 'present']]])->assertNoContent();
        $this->postJson("/api/v1/clubs/$club/activities/awards", ['player_id' => $player, 'award_year' => 2026, 'title' => 'Xuất sắc', 'gift' => 'Áo'])->assertCreated();
        $this->getJson("/api/v1/clubs/$club/activities")->assertOk()->assertJsonPath('data.birthdays.0.name', 'An')->assertJsonPath('data.trainings.0.attendance.0.status', 'present')->assertJsonPath('data.awards.0.gift', 'Áo');
        $this->deleteJson("/api/v1/clubs/$club/teams/$team/players/$player")->assertNoContent();
        $this->patchJson($url, ['players' => []])->assertNoContent();
        $this->getJson("/api/v1/clubs/$club/activities")->assertJsonPath('data.trainings.0.attendance.0.name', 'An');
        $this->deleteJson("/api/v1/clubs/$club/members/$player")->assertNoContent();
        $this->assertDatabaseHas('club_awards', ['club_id' => $club, 'player_id' => null, 'player_name' => 'An']);
        $this->deleteJson("/api/v1/clubs/$club/teams/$team")->assertNoContent();
        $this->assertDatabaseHas('team_trainings', ['id' => $training, 'team_id' => null, 'team_name' => 'Đội']);
        $limited = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $limited->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($limited)->getJson("/api/v1/clubs/$club/activities")->assertForbidden();
        $this->postJson("/api/v1/clubs/$club/activities/celebrations", ['title' => 'Unauthorized', 'event_date' => '2027-01-01'])->assertForbidden();
        $outsider = User::factory()->create(); $this->actingAs($outsider)->getJson("/api/v1/clubs/$club/activities")->assertNotFound();
    }
}
