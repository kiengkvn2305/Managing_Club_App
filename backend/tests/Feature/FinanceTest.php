<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_permissions_and_totals_are_club_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '5'])->assertCreated()->json('data.id');
        $path = "/api/v1/clubs/$club/finance";
        $payload = ['type' => 'income', 'amount' => 100000, 'occurred_on' => '2026-11-18', 'description' => 'Quỹ'];
        $this->postJson($path, $payload)->assertCreated();
        $this->postJson($path, ['type' => 'expense', 'amount' => 30000, 'occurred_on' => '2026-11-18', 'description' => 'Sân'])->assertCreated();
        $this->getJson($path)->assertOk()->assertJsonPath('summary.income', 100000)->assertJsonPath('summary.expense', 30000)->assertJsonPath('summary.balance', 70000);
        $this->postJson($path, $payload + [])->assertCreated();
        $invalid = $payload; $invalid['amount'] = -1;
        $this->postJson($path, $invalid)->assertUnprocessable();
        foreach (['Phó chủ nhiệm CLB', 'HLV', 'Thủ quỹ', 'Media', 'Thành viên'] as $title) {
            $user = User::factory()->create();
            DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => 'member', 'title' => $title, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($user);
            $this->getJson($path)->assertStatus(in_array($title, ['Phó chủ nhiệm CLB', 'HLV', 'Thủ quỹ']) ? 200 : 403);
            $this->postJson($path, $payload)->assertStatus($title === 'Thủ quỹ' ? 201 : 403);
        }
        $this->actingAs(User::factory()->create())->getJson($path)->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/clubs')->assertJsonPath('data.0.can_view_finance', true)->assertJsonPath('data.0.can_manage_finance', true);
        $this->assertDatabaseHas('audit_logs', ['club_id' => $club, 'action' => 'finance.created']);
    }
}
