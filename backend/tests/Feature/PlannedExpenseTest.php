<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlannedExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_plans_are_editable_from_a_month_and_never_change_balance(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id'); $base = "/api/v1/clubs/$club";
        $id = $this->postJson("$base/finance/planned", ['description' => 'Tiền sân', 'amount' => 1200000, 'notes' => 'Trả cuối tháng', 'month' => '2026-01'])->assertCreated()->json('data.id');
        $this->getJson("$base/finance/planned?from=2026-01&to=2026-03")->assertOk()->assertJsonCount(3, 'data');
        $this->patchJson("$base/finance/planned/$id", ['description' => 'Tiền sân mới', 'amount' => 1500000, 'month' => '2026-03'])->assertNoContent();
        $this->getJson("$base/finance/planned?from=2026-02&to=2026-03")->assertJsonPath('data.0.description', 'Tiền sân')->assertJsonPath('data.0.amount', 1200000)->assertJsonPath('data.1.description', 'Tiền sân mới');
        $this->getJson("$base/finance")->assertJsonPath('summary.expense', 0)->assertJsonPath('summary.remaining', 0);
        $current = DB::table('planned_expenses')->where('club_id', $club)->where('description', 'Tiền sân mới')->value('id');
        $this->deleteJson("$base/finance/planned/$current?month=2026-04")->assertNoContent();
        $this->getJson("$base/finance/planned?from=2026-03&to=2026-04")->assertJsonCount(1, 'data')->assertJsonPath('data.0.month', '2026-03');
        $this->getJson("$base/finance/planned?from=2026-01&to=2027-01")->assertUnprocessable();
        $user = User::factory()->create(); DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->getJson("$base/finance/planned?from=2026-01&to=2026-01")->assertForbidden();
        $this->postJson("$base/finance/planned", ['description' => 'Không được', 'month' => '2026-01'])->assertForbidden();
    }
}
