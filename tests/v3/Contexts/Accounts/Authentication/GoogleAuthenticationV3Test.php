<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\v3\TestCase;

final class GoogleAuthenticationV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google', [
            'client_id' => 'google-client-id',
            'client_secret' => 'google-client-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_matching_email_does_not_link_existing_password_account(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test']);
        $this->fakeGoogleUser('member@example.test', true, 'Existing Member');

        $response = $this->from('/login')->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        self::assertSame(AuthenticationType::Password, $user->refresh()->authentication_type);
        self::assertTrue($user->supportsPasswordAuthentication());
        self::assertFalse(AccountIdentity::query()->where('user_id', $user->id)->exists());
    }

    public function test_verified_google_identity_creates_google_account_without_password(): void
    {
        config()->set('accounts.registration_mode', 'open');
        $this->fakeGoogleUser('new.member@example.test', true, 'New Member');

        $response = $this->get('/auth/google/callback');
        $user = User::query()->where('email', 'new.member@example.test')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        self::assertNotNull($user->email_verified_at);
        self::assertSame('New Member', $user->name);
        self::assertSame(AuthenticationType::Google, $user->authentication_type);
        self::assertNull($user->getRawOriginal('password'));
        self::assertTrue($user->supportsGoogleAuthentication());
        $this->assertDatabaseHas('account_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'new.member@example.test',
        ]);
    }

    public function test_existing_google_subject_is_authoritative_when_provider_email_changes(): void
    {
        $user = User::factory()->google()->create([
            'email' => 'old@example.test',
            'email_verified_at' => now(),
        ]);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'old@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);
        $this->fakeGoogleUser('new@example.test', true, 'Existing Member');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        self::assertSame('new@example.test', AccountIdentity::query()->firstOrFail()->provider_email);
        self::assertSame('old@example.test', $user->refresh()->email);
    }

    public function test_google_email_must_be_verified(): void
    {
        $this->fakeGoogleUser('unverified@example.test', false, 'Unverified Member');

        $response = $this->from('/login')->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        self::assertFalse(User::query()->where('email', 'unverified@example.test')->exists());
    }

    public function test_google_cannot_bypass_invitation_only_registration(): void
    {
        config()->set('accounts.registration_mode', 'invitation');
        $this->fakeGoogleUser('new.member@example.test', true, 'New Member');

        $this->get('/auth/google/callback')->assertForbidden();

        $this->assertGuest();
        self::assertFalse(User::query()->where('email', 'new.member@example.test')->exists());
    }

    private function fakeGoogleUser(string $email, bool $verified, string $name): void
    {
        $socialiteUser = (new SocialiteUser)
            ->setRaw([
                'sub' => 'google-subject-id',
                'name' => $name,
                'email' => $email,
                'email_verified' => $verified,
            ])
            ->map([
                'id' => 'google-subject-id',
                'name' => $name,
                'email' => $email,
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }
}
