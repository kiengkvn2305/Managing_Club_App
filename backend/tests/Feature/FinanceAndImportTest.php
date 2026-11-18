<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceAndImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_are_private_and_finance_filters_cover_only_the_selected_dates(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '5'])->assertCreated()->json('data.id');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $receipt = UploadedFile::fake()->createWithContent('receipt.png', $png);
        $payload = ['type' => 'income', 'amount' => 100, 'occurred_on' => '2026-02-01', 'description' => 'Phí', 'receipt' => $receipt];
        $entry = $this->post("/api/v1/clubs/$club/finance", $payload, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/clubs/$club/finance", ['type' => 'expense', 'amount' => 25, 'occurred_on' => '2026-03-01', 'description' => 'Sân'])->assertCreated();
        $this->getJson("/api/v1/clubs/$club/finance?from=2026-02-01&to=2026-02-28")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.balance', 100)->assertJsonPath('data.0.has_receipt', true)->assertJsonMissingPath('data.0.receipt_path');
        $this->getJson("/api/v1/clubs/$club/finance?group=month")->assertOk()->assertJsonCount(2, 'chart')->assertJsonPath('chart.0.period', '2026-02');
        $this->getJson("/api/v1/clubs/$club/finance?group=year")->assertOk()->assertJsonPath('chart.0.period', '2026');
        $this->get("/api/v1/clubs/$club/finance/$entry/receipt")->assertOk();
        $this->getJson("/api/v1/clubs/$club/finance?from=2026-03-02&to=2026-02-01")->assertUnprocessable();
        $this->actingAs(User::factory()->create())->get("/api/v1/clubs/$club/finance/$entry/receipt")->assertNotFound();
    }

    public function test_member_import_is_validated_atomically(): void
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'format' => '5'])->assertCreated()->json('data.id');
        $url = "/api/v1/clubs/$club/members/import";
        $rows = [['full_name' => 'An', 'email' => 'an@example.test'], ['full_name' => 'Binh', 'email' => 'an@example.test']];
        $this->postJson($url, ['players' => $rows])->assertUnprocessable();
        $this->assertDatabaseMissing('player_profiles', ['club_id' => $club, 'full_name' => 'An']);
        $rows[1]['email'] = 'binh@example.test';
        $this->postJson($url, ['players' => $rows])->assertCreated()->assertJsonPath('data.imported', 2);
        $this->assertDatabaseHas('player_profiles', ['club_id' => $club, 'full_name' => 'An']);
        $this->postJson($url, ['players' => [['full_name' => 'Intruder', 'team_id' => 1]]])->assertUnprocessable();
    }

    public function test_team_format_must_belong_to_club_and_cannot_be_removed_while_used(): void
    {
        $this->actingAs(User::factory()->create());
        $club = $this->postJson('/api/v1/clubs', ['name' => 'CLB', 'formats' => ['5']])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Wrong', 'format' => '11'])->assertUnprocessable();
        $team = $this->postJson("/api/v1/clubs/$club/teams", ['name' => 'Team', 'format' => '5'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Team', 'format' => '11'])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club", ['formats' => ['11']])->assertUnprocessable();
        $this->patchJson("/api/v1/clubs/$club", ['formats' => ['5', '11']])->assertOk();
        $this->patchJson("/api/v1/clubs/$club/teams/$team", ['name' => 'Team', 'format' => '11'])->assertOk();
    }
}
