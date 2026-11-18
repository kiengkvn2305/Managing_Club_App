<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_recruitment_validation_and_isolation(): void
    {
        $owner = User::factory()->create(); $this->actingAs($owner);
        $a = $this->postJson('/api/v1/clubs', ['name' => 'A', 'format' => '7'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/clubs', ['name' => 'B', 'format' => '7'])->assertCreated()->json('data.id');
        $base = "/api/v1/clubs/$a/recruitment/posts";
        $postData = ['title' => 'Tuyển tiền đạo', 'position' => 'ST', 'requirements' => 'Chăm chỉ', 'status' => 'open'];
        $this->postJson($base, ['title' => '', 'status' => 'wrong'])->assertUnprocessable();
        $post = $this->postJson($base, $postData)->assertCreated()->json('data.id');
        $this->getJson($base)->assertOk()->assertJsonPath('data.0.title', 'Tuyển tiền đạo');
        $appData = ['full_name' => 'Nguyễn An', 'date_of_birth' => '2000-01-01', 'email' => 'an@example.test', 'phone' => '0900', 'status' => 'new'];
        $this->postJson("$base/$post/applications", array_replace($appData, ['email' => 'invalid']))->assertUnprocessable();
        $candidate = $this->postJson("$base/$post/applications", $appData)->assertCreated()->json('data.id');
        $this->patchJson("$base/$post/applications/$candidate", array_replace($appData, ['status' => 'trial']))->assertNoContent();
        $this->getJson("$base/$post/applications?status=trial")->assertOk()->assertJsonPath('data.0.email', 'an@example.test');
        $this->getJson("/api/v1/clubs/$b/recruitment/posts/$post/applications")->assertNotFound();
        $this->patchJson("/api/v1/clubs/$b/recruitment/posts/$post", $postData)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['club_id' => $a, 'action' => 'recruitment.application_updated']);

        $member = User::factory()->create();
        DB::table('club_members')->insert(['club_id' => $a, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member)->getJson("$base/$post/applications")->assertForbidden();
        $this->postJson("$base/$post/applications", $appData)->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson($base)->assertNotFound();
    }
}
