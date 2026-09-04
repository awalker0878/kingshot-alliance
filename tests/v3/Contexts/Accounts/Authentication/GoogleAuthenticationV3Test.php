<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

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

        $response = $this
            ->withSession($this->googleOperation('login'))
            ->from('/login')
            ->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        self::assertTrue($user->refresh()->supportsPasswordAuthentication());
        self::assertFalse($user->supportsGoogleAuthentication());
        self::assertFalse(AccountIdentity::query()->where('user_id', $user->id)->exists());
    }

    public function test_explicit_google_registration_creates_user_without_password(): void
    {
        config()->set('accounts.registration_mode', 'open');
        $this->fakeGoogleUser('new.member@example.test', true, 'New Member');

        $response = $this
            ->withSession($this->googleOperation('register'))
            ->get('/auth/google/callback');
        $user = User::query()->where('email', 'new.member@example.test')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        self::assertNotNull($user->email_verified_at);
        self::assertSame('New Member', $user->name);
        self::assertNull($user->getRawOriginal('password'));
        self::assertTrue($user->supportsGoogleAuthentication());
        $this->assertDatabaseHas('account_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'new.member@example.test',
        ]);
    }

    public function test_existing_google_subject_stays_authoritative_without_replacing_account_email(): void
    {
        $user = User::factory()->withoutPassword()->create([
            'email' => 'account@example.test',
            'email_verified_at' => now(),
        ]);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'old-provider@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);
        $this->fakeGoogleUser('new-provider@example.test', true, 'Existing Member');

        $response = $this
            ->withSession($this->googleOperation('login'))
            ->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $identity = AccountIdentity::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame('google-subject-id', $identity->provider_subject);
        self::assertSame('new-provider@example.test', $identity->provider_email);
        self::assertSame('account@example.test', $user->refresh()->email);
    }

    public function test_google_email_must_be_verified(): void
    {
        $this->fakeGoogleUser('unverified@example.test', false, 'Unverified Member');

        $response = $this
            ->withSession($this->googleOperation('register'))
            ->from('/register')
            ->get('/auth/google/callback');

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        self::assertFalse(User::query()->where('email', 'unverified@example.test')->exists());
    }

    public function test_google_registration_cannot_bypass_invitation_only_mode(): void
    {
        config()->set('accounts.registration_mode', 'invitation');
        $this->fakeGoogleUser('new.member@example.test', true, 'New Member');

        $this
            ->withSession($this->googleOperation('register'))
            ->get('/auth/google/callback')
            ->assertForbidden();

        $this->assertGuest();
        self::assertFalse(User::query()->where('email', 'new.member@example.test')->exists());
    }

    /** @return array<string,array{intent:string,user_id:null,invitation_token:null,started_at:int}> */
    private function googleOperation(string $intent): array
    {
        return [
            'accounts.google_operation' => [
                'intent' => $intent,
                'user_id' => null,
                'invitation_token' => null,
                'started_at' => now()->timestamp,
            ],
        ];
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
