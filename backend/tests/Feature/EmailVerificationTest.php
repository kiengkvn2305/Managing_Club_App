<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyAccountEmail as VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_email_link_verifies_only_its_owner_and_cannot_be_forged(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->postJson('/api/v1/auth/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->getJson($url.'&tampered=1')->assertForbidden();
        $this->getJson($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_changing_email_requires_password_and_revokes_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $this->actingAs($user)->patchJson('/api/v1/auth/profile', ['email' => 'new@example.test'])->assertUnprocessable();
        $this->patchJson('/api/v1/auth/profile', ['email' => 'new@example.test', 'current_password' => 'Password123!'])->assertOk();
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->postJson('/api/v1/invitations/accept', ['token' => str_repeat('x', 64)])->assertForbidden();
    }
}
