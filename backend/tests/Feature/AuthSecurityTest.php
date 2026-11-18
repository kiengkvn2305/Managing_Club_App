<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_credentials_produce_validation_errors_instead_of_server_errors(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ['invalid'], 'password' => ['invalid']])->assertUnprocessable();
        $this->postJson('/api/v1/auth/register', ['name' => 'Invalid', 'email' => ['invalid'], 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertUnprocessable();
        foreach ([str_repeat('界', 25), "Password123!\0invalid"] as $password) {
            $this->postJson('/api/v1/auth/register', ['name' => 'Invalid', 'email' => 'invalid@example.test', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable();
        }
    }

    public function test_session_only_api_rejects_unsupported_bearer_authentication(): void
    {
        $this->getJson('/api/v1/clubs', ['Authorization' => 'Bearer 1|'.str_repeat('a', 64)])->assertUnauthorized();
    }

    public function test_registration_hashes_password_and_ignores_privilege_injection(): void
    {
        $r = $this->postJson('/api/v1/auth/register', ['name' => 'Owner', 'email' => 'OWNER@example.test', 'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!', 'is_system_admin' => true, 'role' => 'owner'])->assertCreated()->assertJsonPath('data.email', 'owner@example.test');
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        $this->assertFalse($user->is_system_admin);
        $this->assertArrayNotHasKey('password', $r->json('data'));
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_registration_validation_is_atomic(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Invalid', 'email' => 'bad', 'password' => 'Password123', 'password_confirmation' => 'different'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_profile_and_logout_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $other = User::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])->assertOk();
        $this->patchJson('/api/v1/auth/profile', ['name' => 'Updated', 'user_id' => $other->id, 'is_system_admin' => true])->assertOk();
        $this->assertSame('Updated', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_system_admin);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_bad_credentials_and_inactive_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('Password123!')]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
        $this->getJson('/api/v1/clubs')->assertUnauthorized();
        $this->actingAs($user)->getJson('/api/v1/clubs')->assertForbidden();
    }

    public function test_failed_login_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'rate@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'rate@example.test', 'password' => 'wrong'])->assertStatus(429);
    }
}
