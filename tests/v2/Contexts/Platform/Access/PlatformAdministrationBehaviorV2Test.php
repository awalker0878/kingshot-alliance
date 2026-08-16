<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\Access;

use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use App\Contexts\Platform\Access\Services\PlatformAuthorization;
use App\Contexts\Platform\Access\Services\PlatformWriteState;
use App\Contexts\Platform\Actions\ManagePlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class PlatformAdministrationBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_bootstrap_grant_and_revoke_are_explicit(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->user();
        $second = $factory->user();
        $manager = app(ManagePlatformAdministrator::class);

        $firstGrant = $manager->grant($first);
        self::assertTrue(PlatformAdministrator::activeFor($first));
        self::assertNull($firstGrant->granted_by_user_id);

        $secondGrant = $manager->grant($second, $first);
        self::assertTrue(PlatformAdministrator::activeFor($second));
        $revoked = $manager->revoke($first, $secondGrant);
        self::assertNotNull($revoked->revoked_at);
        self::assertFalse(PlatformAdministrator::activeFor($second));

        $this->expectException(InvalidArgumentException::class);
        $manager->revoke($first, $firstGrant);
    }

    public function test_platform_write_state_and_authorization_require_an_active_grant(): void
    {
        $user = (new ScenarioFactory)->user();
        $authorization = app(PlatformAuthorization::class);
        $writeState = app(PlatformWriteState::class);

        try {
            DB::transaction(function () use ($authorization, $writeState, $user): void {
                $authorization->authorizeContext($writeState->lock($user));
            });
            self::fail('A user without an active Platform Administrator grant must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        app(ManagePlatformAdministrator::class)->grant($user);
        $context = DB::transaction(
            fn () => $authorization->authorizeContext($writeState->lock($user)),
        );

        self::assertSame((int) $user->id, (int) $context->actor->id);
        self::assertSame((int) $user->id, (int) $context->grant->user_id);
    }
}
