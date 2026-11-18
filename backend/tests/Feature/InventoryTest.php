<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_history_limits_permissions_and_tenant_isolation(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club A', 'format' => '7'])->assertCreated()->json('data.id');
        $base = "/api/v1/clubs/$club/inventory";
        $payload = ['name' => 'Bóng', 'category' => 'Tập luyện', 'unit' => 'quả', 'kind' => 'asset', 'low_stock_threshold' => 2];
        $id = $this->postJson($base, $payload)->assertCreated()->json('data.id');
        $this->postJson("$base/$id/movements", ['type' => 'in', 'quantity' => 5, 'reason' => 'Mua mới'])->assertCreated()->assertJsonPath('data.quantity', 5);
        $this->patchJson("$base/$id", array_replace($payload, ['unit' => 'chiếc']))->assertUnprocessable();
        $this->postJson("$base/$id/movements", ['type' => 'out', 'quantity' => 6, 'reason' => 'Xuất'])->assertUnprocessable();
        $this->assertDatabaseHas('inventory_items', ['id' => $id, 'quantity' => 5]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->postJson("$base/$id/movements", ['type' => 'out', 'quantity' => 3, 'reason' => 'Hỏng'])->assertCreated()->assertJsonPath('data.quantity', 2);
        $this->getJson("$base?low_stock=1")->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("$base/$id")->assertUnprocessable();
        $this->postJson("$base/$id/movements", ['type' => 'adjust', 'quantity' => 0, 'reason' => 'Kiểm kê'])->assertCreated();
        $this->getJson("$base/$id/movements")->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.balance_after', 0);
        $this->deleteJson("$base/$id")->assertNoContent();
        $this->getJson($base)->assertJsonCount(0, 'data');
        $this->getJson("$base/$id/movements")->assertJsonCount(3, 'data');
        $this->assertDatabaseHas('inventory_items', ['id' => $id, 'archived' => true]);
        $this->getJson("$base?archived=1")->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("$base/$id/restore")->assertNoContent();
        $this->getJson($base)->assertJsonPath('data.0.id', $id);

        $equipment = User::factory()->create(); $member = User::factory()->create();
        foreach ([[$equipment, 'equipment_manager'], [$member, 'member']] as [$user, $role]) DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($equipment)->postJson($base, $payload)->assertCreated();
        $this->actingAs($member)->getJson($base)->assertForbidden();
        $this->postJson($base, $payload)->assertForbidden();
        $foreign = User::factory()->create(); $this->actingAs($foreign);
        $other = $this->postJson('/api/v1/clubs', ['name' => 'Club B', 'format' => '7'])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/clubs/$other/inventory/$id/movements")->assertNotFound();
        $this->postJson("/api/v1/clubs/$other/inventory/$id/movements", ['type' => 'in', 'quantity' => 1, 'reason' => 'Sai CLB'])->assertNotFound();
        $this->getJson($base)->assertNotFound();
    }

    public function test_database_rejects_movement_with_another_clubs_item(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $a = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $item = $this->postJson("/api/v1/clubs/$a/inventory", ['name' => 'Bóng', 'unit' => 'quả', 'kind' => 'asset', 'low_stock_threshold' => 0])->assertCreated()->json('data.id');
        $this->expectException(QueryException::class);
        DB::table('inventory_movements')->insert(['club_id' => $b, 'inventory_item_id' => $item, 'actor_id' => $owner->id, 'type' => 'in', 'change' => 1, 'balance_after' => 1, 'reason' => 'Sai CLB', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_assets_are_assigned_and_partially_returned_with_history(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $other = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = DB::table('player_profiles')->insertGetId(['club_id' => $club, 'full_name' => 'Nguyễn An', 'email' => 'an@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $foreign = DB::table('player_profiles')->insertGetId(['club_id' => $other, 'full_name' => 'Khác', 'created_at' => now(), 'updated_at' => now()]);
        $base = "/api/v1/clubs/$club/inventory";
        $asset = $this->postJson($base, ['name' => 'Áo', 'unit' => 'cái', 'kind' => 'asset', 'low_stock_threshold' => 1])->assertCreated()->json('data.id');
        $this->postJson("$base/$asset/movements", ['type' => 'in', 'quantity' => 4, 'reason' => 'Mua'])->assertCreated();
        $this->getJson("$base/members?search=An")->assertOk()->assertJsonPath('data.0.id', $player)->assertJsonCount(1, 'data');
        $this->postJson("$base/$asset/assignments", ['player_id' => $foreign, 'quantity' => 1, 'reason' => 'Cấp'])->assertUnprocessable();
        $this->postJson("$base/$asset/assignments", ['player_id' => $player, 'quantity' => 5, 'reason' => 'Cấp'])->assertUnprocessable();
        $loan = $this->postJson("$base/$asset/assignments", ['player_id' => $player, 'quantity' => 4, 'reason' => 'Cấp'])->assertCreated()->assertJsonPath('data.quantity', 0)->json('data.id');
        $this->deleteJson("$base/$asset")->assertUnprocessable();
        $this->postJson("$base/$asset/assignments/$loan/return", ['quantity' => 5, 'reason' => 'Thu'])->assertUnprocessable();
        $this->postJson("$base/$asset/assignments/$loan/return", ['quantity' => 2, 'reason' => 'Thu một phần'])->assertCreated()->assertJsonPath('data.remaining', 2)->assertJsonPath('data.quantity', 2);
        $this->getJson("$base/$asset/assignments")->assertOk()->assertJsonPath('data.0.player_name', 'Nguyễn An')->assertJsonPath('data.0.remaining', 2);
        $this->postJson("$base/$asset/assignments/$loan/return", ['quantity' => 3, 'reason' => 'Quá số'])->assertUnprocessable();
        $this->postJson("$base/$asset/assignments/$loan/return", ['quantity' => 2, 'reason' => 'Thu hết'])->assertCreated()->assertJsonPath('data.remaining', 0);
        $this->getJson("$base/$asset/movements")->assertJsonCount(4, 'data')->assertJsonPath('data.0.type', 'return');
        $this->deleteJson("/api/v1/clubs/$club/members/$player")->assertUnprocessable();
        $this->assertDatabaseHas('inventory_assignments', ['id' => $loan, 'remaining' => 0]);
    }

    public function test_assignment_foreign_keys_reject_cross_club_player(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $a = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $player = DB::table('player_profiles')->insertGetId(['club_id' => $b, 'full_name' => 'Foreign', 'created_at' => now(), 'updated_at' => now()]);
        $item = $this->postJson("/api/v1/clubs/$a/inventory", ['name' => 'Áo', 'unit' => 'cái', 'kind' => 'asset', 'low_stock_threshold' => 0])->assertCreated()->json('data.id');
        $this->expectException(QueryException::class);
        DB::table('inventory_assignments')->insert(['club_id' => $a, 'inventory_item_id' => $item, 'player_id' => $player, 'player_name' => 'Foreign', 'quantity' => 1, 'remaining' => 1, 'actor_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_equipment_role_receives_only_minimal_candidate_details(): void
    {
        $owner = User::factory()->create(); $equipment = User::factory()->create(); $this->actingAs($owner);
        $club = $this->postJson('/api/v1/clubs', ['name' => 'Club', 'format' => '7'])->assertCreated()->json('data.id');
        $player = $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'An', 'email' => 'an@example.test', 'date_of_birth' => '2000-01-01'])->assertCreated()->json('data.id');
        DB::table('club_members')->insert(['club_id' => $club, 'user_id' => $equipment->id, 'role' => 'equipment_manager', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($equipment)->getJson("/api/v1/clubs/$club/inventory/members?search=An")->assertOk()->assertJsonPath('data.0.id', $player)->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.date_of_birth');
    }
}
