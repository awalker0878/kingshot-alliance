<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Administration;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlatformAdministrationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_bootstrap_grant_and_revoke_are_explicit(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->account();
        $second = $factory->account();
        $accounts = app(AccountIdentityQuery::class);
        $manager = app(ManagePlatformAdministrator::class);

        $firstGrantId = $manager->grant($first->userId);
        self::assertTrue(PlatformAdministrator::activeForUserId($first->userId));
        self::assertNull(PlatformAdministrator::query()->findOrFail($firstGrantId)->granted_by_user_id);

        $firstIdentity = $accounts->require($first->userId);
        $secondGrantId = $manager->grant($second->userId, $firstIdentity);
        self::assertTrue(PlatformAdministrator::activeForUserId($second->userId));

        $revokedGrantId = $manager->revoke($firstIdentity, $secondGrantId);
        self::assertSame($secondGrantId, $revokedGrantId);
        self::assertFalse(PlatformAdministrator::activeForUserId($second->userId));

        $this->expectException(InvalidArgumentException::class);
        $manager->revoke($firstIdentity, $firstGrantId);
    }

    public function test_platform_write_state_and_authorization_require_an_active_grant(): void
    {
        $account = (new ScenarioFactory)->account();
        $identity = app(AccountIdentityQuery::class)->require($account->userId);
        $authorization = app(PlatformAuthorization::class);
        $writeState = app(PlatformWriteState::class);

        try {
            DB::transaction(function () use ($authorization, $writeState, $identity): void {
                $authorization->authorizeContext($writeState->lock($identity));
            });
            self::fail('A user without an active Platform Administrator grant must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $grantId = app(ManagePlatformAdministrator::class)->grant($account->userId);
        $context = DB::transaction(
            fn () => $authorization->authorizeContext($writeState->lock($identity)),
        );

        self::assertSame($account->userId, $context->actor->userId);
        self::assertSame($grantId, $context->grantId);
    }
}
