<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class CredentialMutationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_google_only_user_cannot_disconnect_their_final_sign_in_method(): void
    {
        $user = User::factory()->google()->create();

        $this->expectException(ValidationException::class);
        app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google');
    }

    public function test_google_can_be_disconnected_when_password_remains(): void
    {
        $user = User::factory()->create();
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'disconnectable-google-subject',
            'provider_email' => 'provider@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
            'last_used_at' => now(),
        ]);

        app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google');

        self::assertFalse($user->refresh()->supportsGoogleAuthentication());
        self::assertTrue($user->supportsPasswordAuthentication());
    }

    public function test_password_establishment_requires_recent_authentication(): void
    {
        $user = User::factory()->google()->create();

        $this->actingAs($user)
            ->post('/profile/security/password', [
                'password' => 'StrongPassword123',
                'password_confirmation' => 'StrongPassword123',
            ])
            ->assertRedirect(route('password.confirm'));

        self::assertFalse($user->refresh()->supportsPasswordAuthentication());
    }

    public function test_google_user_can_add_password_after_generic_recent_authentication(): void
    {
        $user = User::factory()->google()->create();

        $response = $this->actingAs($user)
            ->withSession([
                'accounts.recent_authentication_at' => now()->timestamp,
                'accounts.recent_authentication_method' => 'google',
            ])
            ->post('/profile/security/password', [
                'password' => 'StrongPassword123',
                'password_confirmation' => 'StrongPassword123',
            ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('actionReceipt');
        $response->assertSessionMissing('accounts.recent_authentication_at');

        $user->refresh();
        self::assertTrue($user->supportsPasswordAuthentication());
        self::assertTrue($user->supportsGoogleAuthentication());
        self::assertTrue(Hash::check('StrongPassword123', (string) $user->getRawOriginal('password')));
        $this->assertDatabaseHas('audit_events', [
            'event' => 'account.password.added',
            'actor_user_id' => $user->id,
        ]);
    }
}
