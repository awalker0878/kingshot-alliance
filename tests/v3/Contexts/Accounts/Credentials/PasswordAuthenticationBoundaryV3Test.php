<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Credentials;

use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\v3\TestCase;

final class PasswordAuthenticationBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_google_account_does_not_receive_password_reset_token(): void
    {
        $user = User::factory()->google()->create(['email' => 'google.only@example.test']);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas(
            'status',
            'If an account exists for that email address, a password reset link has been sent.',
        );
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        self::assertSame(AuthenticationType::Google, $user->authentication_type);
        self::assertNull($user->getRawOriginal('password'));
    }

    public function test_google_account_cannot_consume_existing_reset_token(): void
    {
        $user = User::factory()->google()->create(['email' => 'google.only@example.test']);
        $token = Password::broker()->createToken($user);

        $response = $this->from('/reset-password/'.$token)->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ReplacementSecret123',
            'password_confirmation' => 'ReplacementSecret123',
        ]);

        $response->assertRedirect('/reset-password/'.$token);
        $response->assertSessionHasErrors('email');
        self::assertNull($user->refresh()->getRawOriginal('password'));
    }

    public function test_google_account_cannot_use_password_login(): void
    {
        $user = User::factory()->google()->create(['email' => 'google.only@example.test']);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'AnyLocalSecret123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_local_password_account_still_receives_reset_token(): void
    {
        $user = User::factory()->create(['email' => 'local@example.test']);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        self::assertSame(AuthenticationType::Password, $user->authentication_type);
    }
}
