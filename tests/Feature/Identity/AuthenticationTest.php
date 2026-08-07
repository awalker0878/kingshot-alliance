<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_canonical_email_and_is_authenticated(): void
    {
        config()->set('identity.registration_mode', 'open');

        $response = $this->post('/register', [
            'name' => 'Alliance Owner',
            'email' => 'Owner@Example.COM',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::query()->sole();

        self::assertSame('owner@example.com', $user->email);
        self::assertSame('America/Toronto', $user->timezone);
        self::assertTrue(Hash::check('StrongPassword123', $user->password));
        self::assertFalse($user->hasVerifiedEmail());

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'user.registered',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => (string) $user->id,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'user.registered',
            'aggregate_type' => User::class,
            'aggregate_id' => (string) $user->id,
        ]);
        self::assertSame(1, AuditEvent::query()->count());
        self::assertSame(1, OutboxMessage::query()->count());
    }

    public function test_open_registration_can_be_disabled(): void
    {
        config()->set('identity.registration_mode', 'invitation_only');

        $response = $this->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
        ]);

        $response->assertForbidden();
        self::assertSame(0, User::query()->count());
    }

    public function test_user_can_login_case_insensitively_and_logout_with_audit_events(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'StrongPassword123',
        ]);

        $login = $this->post('/login', [
            'email' => 'MEMBER@EXAMPLE.COM',
            'password' => 'StrongPassword123',
            'remember' => true,
        ]);

        $login->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.login',
        ]);

        $logout = $this->delete('/logout');

        $logout->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.logout',
        ]);
    }

    public function test_invalid_credentials_do_not_authenticate_or_reveal_account_state(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'member@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'The provided credentials are incorrect.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_events', [
            'event' => 'auth.login',
        ]);
    }
}
