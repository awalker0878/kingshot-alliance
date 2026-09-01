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

final class RecentAuthenticationV3Test extends TestCase
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

    public function test_password_account_is_sent_to_password_confirmation_when_recent_proof_is_stale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/two-factor')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_google_account_is_sent_to_google_reauthentication_when_recent_proof_is_stale(): void
    {
        $user = User::factory()->google()->create();

        $this->actingAs($user)
            ->post('/profile/two-factor')
            ->assertRedirect(route('auth.google.reauthenticate'));
    }

    public function test_google_callback_can_refresh_recent_authentication_only_for_matching_subject(): void
    {
        $user = User::factory()->google()->create(['email' => 'member@example.test']);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'member@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);

        $socialiteUser = (new SocialiteUser)
            ->setRaw([
                'sub' => 'google-subject-id',
                'name' => 'Member',
                'email' => 'member@example.test',
                'email_verified' => true,
            ])
            ->map([
                'id' => 'google-subject-id',
                'name' => 'Member',
                'email' => 'member@example.test',
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->actingAs($user)
            ->withSession([
                'accounts.google_reauthentication_user_id' => $user->id,
                'url.intended' => route('profile.show'),
            ])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('profile.show'));
        self::assertGreaterThan(0, (int) session('accounts.google_reauthenticated_at'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_reauthentication_rejects_a_different_google_subject(): void
    {
        $user = User::factory()->google()->create(['email' => 'member@example.test']);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'expected-subject',
            'provider_email' => 'member@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);

        $socialiteUser = (new SocialiteUser)
            ->setRaw([
                'sub' => 'other-subject',
                'name' => 'Member',
                'email' => 'member@example.test',
                'email_verified' => true,
            ])
            ->map([
                'id' => 'other-subject',
                'name' => 'Member',
                'email' => 'member@example.test',
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->actingAs($user)
            ->withSession(['accounts.google_reauthentication_user_id' => $user->id])
            ->get('/auth/google/callback')
            ->assertForbidden();

        self::assertSame(0, (int) session('accounts.google_reauthenticated_at', 0));
    }
}
