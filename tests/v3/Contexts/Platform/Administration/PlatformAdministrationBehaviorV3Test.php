<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Administration;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\Administration\Actions\RetryOutboxMessage;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\ReadModels\PlatformAdministration\PlatformAdministrationQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function test_platform_dashboard_includes_privacy_safe_gift_code_workspace_health(): void
    {
        config()->set('game_world.gift_codes.redemption_workspace', true);
        $dashboard = app(PlatformAdministrationQuery::class)->dashboard();
        $health = $dashboard['diagnostics']['giftCodeWorkspace'] ?? null;

        self::assertIsArray($health);
        self::assertTrue($health['workspaceEnabled'] ?? false);
        self::assertArrayHasKey('activeSessions', $health);
        self::assertArrayHasKey('staleSessions', $health);
        self::assertArrayHasKey('readyItems', $health);
        self::assertArrayHasKey('retryWaitItems', $health);
        self::assertArrayHasKey('unavailableItems', $health);
        self::assertArrayHasKey('dueReminders', $health);
        self::assertArrayHasKey('pushEligibleSources', $health);
        self::assertArrayNotHasKey('playerIds', $health);
        self::assertArrayNotHasKey('userIds', $health);
    }

    public function test_exhausted_outbox_work_requires_an_audited_operator_retry(): void
    {
        $account = (new ScenarioFactory)->account();
        $identity = app(AccountIdentityQuery::class)->require($account->userId);
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $maximumAttempts = max(1, (int) config('operations.outbox.maximum_attempts', 10));
        $message = OutboxMessage::query()->create([
            'alliance_id' => null,
            'partition_key' => 'platform',
            'event_type' => 'test.exhausted',
            'aggregate_type' => 'TestAggregate',
            'aggregate_id' => 'aggregate-1',
            'idempotency_key' => 'outbox-exhausted-test',
            'payload' => ['safe' => true],
            'occurred_at' => now()->subHour(),
            'available_at' => now()->subHour(),
            'attempts' => $maximumAttempts,
            'last_error' => 'Sensitive provider detail must never reach diagnostics.',
        ]);

        app(PublishOutboxBatch::class)->handle();
        $exhausted = $message->fresh();
        self::assertNull($exhausted?->published_at);
        self::assertSame($maximumAttempts, $exhausted?->attempts);

        app(RetryOutboxMessage::class)->handle($identity, (string) $message->id);
        $retried = $message->fresh();
        self::assertSame(0, $retried?->attempts);
        self::assertNull($retried?->last_error);

        $audit = AuditEvent::query()
            ->where('event', 'platform.outbox.retry_released')
            ->where('subject_id', $message->id)
            ->firstOrFail();
        self::assertSame($maximumAttempts, $audit->metadata['previous_attempts'] ?? null);
        self::assertSame(16, strlen((string) ($audit->metadata['error_fingerprint'] ?? '')));
        self::assertStringNotContainsString('Sensitive provider detail', json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_operator_cannot_release_work_inside_the_automatic_retry_budget(): void
    {
        $account = (new ScenarioFactory)->account();
        $identity = app(AccountIdentityQuery::class)->require($account->userId);
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $message = OutboxMessage::query()->create([
            'alliance_id' => null,
            'partition_key' => 'platform',
            'event_type' => 'test.retry-pending',
            'aggregate_type' => 'TestAggregate',
            'aggregate_id' => 'aggregate-2',
            'idempotency_key' => 'outbox-retry-pending-test',
            'payload' => ['safe' => true],
            'occurred_at' => now()->subHour(),
            'available_at' => now()->addMinute(),
            'attempts' => 1,
            'last_error' => 'Transient error',
        ]);

        $this->expectException(ValidationException::class);
        app(RetryOutboxMessage::class)->handle($identity, (string) $message->id);
    }
}
