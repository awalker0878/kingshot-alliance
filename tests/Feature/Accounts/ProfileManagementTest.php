<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Contexts\Accounts\Models\User;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_and_email_change_requires_reverification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'New Name',
            'email' => 'NEW@example.com',
            'timezone' => 'America/Toronto',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user->refresh();
        self::assertSame('New Name', $user->name);
        self::assertSame('new@example.com', $user->email);
        self::assertSame('America/Toronto', $user->timezone);
        self::assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'profile.updated',
        ]);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'profile.updated')->exists());
    }

    public function test_password_update_revokes_tokens_preserves_current_login_and_is_audited(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);
        $user->createToken('old-token');

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
        self::assertTrue(Hash::check('NewPassword123', $user->refresh()->password));
        self::assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'profile.password.updated',
        ]);
        self::assertTrue(OutboxMessage::query()->where('event_type', 'profile.password.updated')->exists());
    }

    public function test_wrong_current_password_cannot_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $response = $this->actingAs($user)
            ->from('/profile')
            ->put('/profile/password', [
                'current_password' => 'WrongPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors('current_password');
        self::assertTrue(Hash::check('OldPassword123', $user->refresh()->password));
    }

    public function test_user_can_revoke_other_sessions_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'StrongPassword123',
        ]);

        $response = $this->actingAs($user)->delete('/profile/sessions/other', [
            'password' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('profile.show'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.other_sessions.revoked',
        ]);
    }
}
