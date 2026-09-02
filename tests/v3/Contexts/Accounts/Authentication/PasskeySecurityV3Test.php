<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
