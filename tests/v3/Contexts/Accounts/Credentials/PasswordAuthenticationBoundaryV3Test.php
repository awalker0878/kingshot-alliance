<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Credentials;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\v3\TestCase;

final class PasswordAuthenticationBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_google_only_account_does_not_receive_password_reset_token(): void
    {
        User::factory()->create([
            'email' => 'google.only@example.test',
            'password_authentication_enabled' => false,
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'google.only@example.test',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas(
            'status',
            'If an account exists for that email address, a password reset link has been sent.',
        );
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'google.only@example.test',
        ]);
    }

    public function test_google_only_account_cannot_consume_existing_reset_token(): void
    {
        $user = User::factory()->create([
            'email' => 'google.only@example.test',
            'password' => 'KnownLocalSecret123',
            'password_authentication_enabled' => false,
        ]);
        $originalPasswordHash = (string) $user->password;
        $token = Password::broker()->createToken($user);

        $response = $this->from('/reset-password/'.$token)->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ReplacementSecret123',
            'password_confirmation' => 'ReplacementSecret123',
        ]);

        $response->assertRedirect('/reset-password/'.$token);
        $response->assertSessionHasErrors('email');
        self::assertSame($originalPasswordHash, (string) $user->refresh()->password);
    }

    public function test_google_only_account_cannot_use_password_login_even_if_internal_password_is_known(): void
    {
        $user = User::factory()->create([
            'email' => 'google.only@example.test',
            'password' => Hash::make('KnownLocalSecret123'),
            'password_authentication_enabled' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'KnownLocalSecret123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_local_password_account_still_receives_reset_token(): void
    {
        User::factory()->create([
            'email' => 'local@example.test',
            'password_authentication_enabled' => true,
        ]);

        $this->post('/forgot-password', [
            'email' => 'local@example.test',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'local@example.test',
        ]);
    }
}
