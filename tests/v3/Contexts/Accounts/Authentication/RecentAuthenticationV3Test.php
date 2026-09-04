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

    public function test_any_account_with_stale_recent_proof_is_sent_to_generic_confirmation(): void
    {
        $passwordUser = User::factory()->create();
        $googleUser = User::factory()->google()->create();

        $this->actingAs($passwordUser)
            ->post('/profile/two-factor')
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($googleUser)
            ->post('/profile/two-factor')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_google_reauthentication_marks_generic_recent_proof_only_for_matching_subject(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'member@example.test']);
        $identity = AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-subject-id',
            'provider_email' => 'member@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);
        $this->fakeGoogleUser('member@example.test', 'google-subject-id');

        $response = $this->actingAs($user)
            ->withSession([
                'accounts.google_operation' => [
                    'intent' => 'reauthenticate',
                    'user_id' => $user->id,
                    'invitation_token' => null,
                    'started_at' => now()->timestamp,
                ],
                'url.intended' => route('profile.show'),
            ])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('profile.show'));
        self::assertSame('google', session('accounts.recent_authentication_method'));
        self::assertSame((string) $identity->id, session('accounts.recent_authentication_credential'));
        self::assertGreaterThan(0, (int) session('accounts.recent_authentication_at'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_reauthentication_rejects_a_different_subject_without_recent_proof(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'member@example.test']);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'expected-subject',
            'provider_email' => 'member@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);
        $this->fakeGoogleUser('member@example.test', 'other-subject');

        $this->actingAs($user)
            ->withSession([
                'accounts.google_operation' => [
                    'intent' => 'reauthenticate',
                    'user_id' => $user->id,
                    'invitation_token' => null,
                    'started_at' => now()->timestamp,
                ],
            ])
            ->get('/auth/google/callback')
            ->assertForbidden();

        self::assertSame(0, (int) session('accounts.recent_authentication_at', 0));
    }

    private function fakeGoogleUser(string $email, string $subject): void
    {
        $socialiteUser = (new SocialiteUser)
            ->setRaw([
                'sub' => $subject,
                'name' => 'Member',
                'email' => $email,
                'email_verified' => true,
            ])
            ->map([
                'id' => $subject,
                'name' => 'Member',
                'email' => $email,
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
