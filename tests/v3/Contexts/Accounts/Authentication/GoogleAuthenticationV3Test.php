<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

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

    public function test_verified_google_email_signs_in_existing_account(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.test',
            'email_verified_at' => null,
        ]);

        $this->fakeGoogleUser('member@example.test', true, 'Existing Member');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        self::assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_verified_google_email_creates_verified_account_when_registration_is_open(): void
    {
        config()->set('accounts.registration_mode', 'open');
        $this->fakeGoogleUser('new.member@example.test', true, 'New Member');

        $response = $this->get('/auth/google/callback');

        $user = User::query()->where('email', 'new.member@example.test')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        self::assertNotNull($user->email_verified_at);
        self::assertSame('New Member', $user->name);
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
