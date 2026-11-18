<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_generic_and_notification_uses_configured_frontend(): void
    {
        Notification::fake(); config(['app.frontend_url' => 'https://club.example']);
        $user = User::factory()->create(['email' => 'member@example.test']);
        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk();
        $this->assertSame($a->json('message'), $b->json('message'));
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            return str_starts_with($url, 'https://club.example/#reset-password?') && str_contains($url, 'token=') && str_contains($url, 'email=member%40example.test');
        });
    }

    public function test_token_is_single_use_and_invalid_or_inactive_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $token = Password::broker()->createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['token' => 'invalid']))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)]))->assertUnprocessable();
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertNoContent();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $inactive = User::factory()->create(['is_active' => false]); $inactiveToken = Password::broker()->createToken($inactive);
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['email' => $inactive->email, 'token' => $inactiveToken]))->assertUnprocessable();
    }

    public function test_reset_revokes_database_sessions_for_the_reset_user_only(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(); $other = User::factory()->create();
        foreach ([['first-session', $user->id], ['other-session', $other->id]] as [$id, $userId]) DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => 'first-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }
}
