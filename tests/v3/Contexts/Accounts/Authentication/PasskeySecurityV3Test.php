<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Http\Responses\AccountPasskeyLoginResponse;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\v3\TestCase;

final class PasskeySecurityV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_every_first_party_passkey_route_is_rate_limited(): void
    {
        foreach ([
            'passkey.login-options',
            'passkey.login',
            'passkey.confirm-options',
            'passkey.confirm',
            'passkey.registration-options',
            'passkey.store',
            'passkey.destroy',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            self::assertNotNull($route, $routeName.' must be registered.');
            self::assertContains(
                'throttle:passkeys',
                $route->gatherMiddleware(),
                $routeName.' must use the Accounts passkey rate limiter.',
            );
        }
    }

    public function test_login_does_not_offer_passkeys_for_an_ip_literal_relying_party(): void
    {
        config()->set('passkeys.enabled', true);
        config()->set('passkeys.relying_party_id', '127.0.0.1');
        config()->set('passkeys.allowed_origins', ['http://127.0.0.1:8000']);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Accounts/Access/Login')
                ->where('passkeyAuthEnabled', false));
    }

    public function test_login_offers_passkeys_for_localhost_development(): void
    {
        config()->set('passkeys.enabled', true);
        config()->set('passkeys.relying_party_id', 'localhost');
        config()->set('passkeys.allowed_origins', ['http://localhost:8000']);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Accounts/Access/Login')
                ->where('passkeyAuthEnabled', true));
    }

    public function test_login_does_not_offer_passkeys_when_the_capability_is_disabled(): void
    {
        config()->set('passkeys.enabled', false);
        config()->set('passkeys.relying_party_id', 'kingshot.app');
        config()->set('passkeys.allowed_origins', ['https://kingshot.app']);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Accounts/Access/Login')
                ->where('passkeyAuthEnabled', false));
    }

    public function test_passkey_user_handle_is_stable_opaque_and_not_account_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Handle Test',
            'email' => 'handle@example.test',
        ]);

        $handle = $user->getPasskeyUserHandle();
        self::assertSame(32, strlen($handle));
        self::assertNotSame((string) $user->id, $handle);
        self::assertNotSame('handle@example.test', $handle);

        $user->forceFill([
            'name' => 'Renamed Handle Test',
            'email' => 'changed-handle@example.test',
        ])->saveOrFail();

        self::assertSame($handle, $user->refresh()->getPasskeyUserHandle());
    }

    public function test_passkey_only_user_cannot_remove_their_final_sign_in_method(): void
    {
        $user = User::factory()->withoutPassword()->create();
        $passkey = $this->passkeyFor($user, 'only-passkey');

        self::assertSame(1, app(AccountSignInMethodPolicy::class)->usableMethodCount($user));

        $this->expectException(ValidationException::class);
        $passkey->delete();
    }

    public function test_passkey_can_be_removed_when_password_remains(): void
    {
        $user = User::factory()->create();
        $passkey = $this->passkeyFor($user, 'removable-passkey');

        self::assertSame(2, app(AccountSignInMethodPolicy::class)->usableMethodCount($user));
        self::assertTrue((bool) $passkey->delete());
        self::assertSame(1, app(AccountSignInMethodPolicy::class)->usableMethodCount($user));
    }

    public function test_verified_passkey_response_establishes_generic_recent_proof_without_totp_challenge(): void
    {
        $user = User::factory()->withoutPassword()->create();
        $passkey = $this->passkeyFor($user, 'verified-passkey');
        $session = app('session')->driver();
        $session->start();
        $session->put('accounts.passkey_verified_public_id', (string) $passkey->public_id);

        $request = Request::create('/passkeys/login', 'POST');
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn (): User => $user);

        $response = app(AccountPasskeyLoginResponse::class)->toResponse($request);

        self::assertStringEndsWith('/dashboard', (string) $response->headers->get('Location'));
        self::assertSame('passkey', $session->get('accounts.recent_authentication_method'));
        self::assertSame((string) $passkey->public_id, $session->get('accounts.recent_authentication_credential'));
        self::assertGreaterThan(0, (int) $session->get('accounts.recent_authentication_at'));
        self::assertFalse($session->has('accounts.two_factor_challenge_user_id'));
        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.login',
            'actor_user_id' => $user->id,
        ]);
    }

    private function passkeyFor(User $user, string $credentialId): AccountPasskey
    {
        $passkey = new AccountPasskey;
        $passkey->forceFill([
            'user_id' => $user->id,
            'name' => 'V3 passkey',
            'credential_id' => $credentialId,
            'credential' => ['test' => true],
        ]);
        $passkey->saveOrFail();

        return $passkey;
    }
}
