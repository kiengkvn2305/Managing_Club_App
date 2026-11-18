<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamDuesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamDuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_correction_preserves_other_payments_and_reconciles_income(): void
    {
        CarbonImmutable::setTestNow('2026-05-01 10:00:00 Asia/Ho_Chi_Minh');
        try {
            $owner = User::factory()->create(); $this->actingAs($owner);
            $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '7'])->assertCreated()->json('data.id'); $base = "/api/v1/clubs/$club";
            $team = $this->postJson("$base/teams", ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
            $player = $this->postJson("$base/members", ['full_name' => 'An'])->assertCreated()->json('data.id');
            $this->patchJson("$base/teams/$team", ['name' => 'A', 'dues_amount' => 100, 'dues_month' => '2026-05'])->assertOk();
            $this->patchJson("$base/teams/$team/players", ['player_ids' => [$player]])->assertNoContent();
            $this->postJson("$base/dues", ['player_id' => $player, 'month' => '2026-05', 'months' => 2, 'status' => 'paid'])->assertCreated();
            $data = ['player_id' => $player, 'team_id' => $team, 'month' => '2026-05', 'expected_status' => 'paid', 'status' => 'unpaid', 'reason' => 'Ghi nhầm'];
            $this->patchJson("$base/dues", $data)->assertNoContent();
            $this->getJson("$base/finance?from=2026-05-01&to=2026-05-31")->assertOk()->assertJsonPath('adjustments.data.0.old_status', 'paid')->assertJsonPath('adjustments.data.0.new_status', 'unpaid')->assertJsonPath('adjustments.data.0.reason', 'Ghi nhầm')->assertJsonPath('adjustments.data.0.player_name', 'An');
            $this->getJson("$base/dues?month=2026-05")->assertJsonPath('data.0.teams.0.status', 'debt');
            $this->getJson("$base/dues?month=2026-06")->assertJsonPath('data.0.teams.0.status', 'paid');
            $this->getJson("$base/finance")->assertJsonPath('summary.income', 100);
            $this->patchJson("$base/dues", $data)->assertUnprocessable();
            $data['expected_status'] = 'unpaid'; $data['status'] = 'exempt';
            $this->patchJson("$base/dues", $data)->assertNoContent();
            $this->getJson("$base/finance")->assertJsonPath('summary.income', 100);
            $data['expected_status'] = 'exempt'; $data['status'] = 'paid';
            $this->patchJson("$base/dues", $data)->assertNoContent();
            $this->getJson("$base/finance")->assertJsonPath('summary.income', 200);
            $this->assertDatabaseCount('team_dues_adjustments', 3);
            $this->actingAs(User::factory()->create())->patchJson("$base/dues", $data)->assertNotFound();
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_cutoff_dates(): void
    {
        $this->assertSame('2026-05-01', TeamDuesService::rateMonth('2026-05-01', CarbonImmutable::parse('2026-05-02')));
        $this->assertSame('2026-06-01', TeamDuesService::rateMonth('2026-05-01', CarbonImmutable::parse('2026-05-03')));
        $this->assertSame('2026-05-01', TeamDuesService::startMonth('2026-05-14'));
        $this->assertSame('2026-06-01', TeamDuesService::startMonth('2026-05-15'));
    }

    public function test_treasurer_can_change_a_rate_without_roster_permissions(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '7'])->assertCreated()->json('data.id');
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $user = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => 'member', 'title' => 'Thủ quỹ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->patchJson("/api/v1/clubs/$club/teams/$team/dues-rate", ['dues_amount' => 100000, 'dues_month' => '2027-01'])->assertNoContent();
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Changed'])->assertForbidden();
    }

    public function test_multiple_teams_payment_advance_exemption_and_tenant_access(): void
    {
        CarbonImmutable::setTestNow('2026-05-01 10:00:00 Asia/Ho_Chi_Minh');
        try {
            $owner = User::factory()->create(); $this->actingAs($owner);
            $club = $this->postJson('/api/v1/clubs', ['name' => 'Test', 'formats' => ['7']])->assertCreated()->json('data.id');
            $base = "/api/v1/clubs/$club";
            $one = $this->postJson("$base/teams", ['name' => 'One', 'format' => '7'])->assertCreated()->json('data.id');
            $two = $this->postJson("$base/teams", ['name' => 'Two', 'format' => '7'])->assertCreated()->json('data.id');
            $player = $this->postJson("$base/members", ['full_name' => 'An', 'status' => 'active'])->assertCreated()->json('data.id');
            foreach ([[$one, 'One', 100], [$two, 'Two', 200]] as [$team, $name, $amount]) {
                $this->patchJson("$base/teams/$team", ['name' => $name, 'dues_amount' => $amount, 'dues_month' => '2026-05'])->assertOk();
                $this->patchJson("$base/teams/$team/players", ['player_ids' => [$player]])->assertNoContent();
            }
            $this->getJson("$base/dues?month=2026-05")->assertOk()->assertJsonPath('data.0.total', 300)->assertJsonPath('data.0.debt', 300);
            $this->postJson("$base/dues", ['player_id' => $player, 'month' => '2026-05', 'months' => 2, 'status' => 'paid'])->assertCreated();
            $this->getJson("$base/teams/$one/dues")->assertJsonPath("data.$player.status", 'paid')->assertJsonPath("data.$player.upcoming.0.month", '2026-06')->assertJsonPath("data.$player.upcoming.0.status", 'paid');
            $this->getJson("$base/dues?month=2026-06")->assertJsonPath('data.0.debt', 0)->assertJsonPath('data.0.teams.0.status', 'paid');
            $this->getJson("$base/finance")->assertJsonPath('summary.income', 600);
            $this->postJson("$base/dues", ['player_id' => $player, 'month' => '2026-06', 'months' => 1, 'status' => 'paid'])->assertUnprocessable();
            $this->postJson("$base/dues", ['player_id' => $player, 'month' => '2026-07', 'months' => 1, 'status' => 'exempt'])->assertCreated();
            $this->getJson("$base/dues?month=2026-07")->assertJsonPath('data.0.teams.0.status', 'exempt');
            $this->getJson("$base/finance")->assertJsonPath('summary.income', 600);
            $this->patchJson("$base/teams/$one", ['name' => 'One', 'dues_amount' => 300, 'dues_month' => '2026-06'])->assertOk();
            $this->getJson("$base/dues?month=2026-06")->assertJsonPath('data.0.total', 300);
            CarbonImmutable::setTestNow('2026-09-01 10:00:00 Asia/Ho_Chi_Minh');
            $this->getJson("$base/teams/$one/dues")->assertOk()->assertJsonPath("data.$player.months", 2)->assertJsonPath("data.$player.debt", 600);
            $this->getJson("$base/members?team_id=$one&dues_status=debt")->assertJsonPath('data.0.id', $player);
            $this->getJson("$base/members?team_id=$one&dues_status=paid")->assertJsonCount(0, 'data');
            $this->actingAs(User::factory()->create())->getJson("$base/dues?month=2026-05")->assertNotFound();
        } finally { CarbonImmutable::setTestNow(); }
    }
}
