<?php

declare(strict_types=1);

namespace Tests\v3\Shared\Infrastructure;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class InfrastructureBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_audit_and_outbox_are_neutral_durable_infrastructure(): void
    {
        $account = (new ScenarioFactory)->account();
        $identity = app(AccountIdentityQuery::class)->require($account->userId);
        $user = User::query()->findOrFail($account->userId);

        $audit = app(AuditRecorder::class)->record('v3.test.audit', $identity, $user, null, ['source' => 'clean-room']);
        $outbox = app(OutboxRecorder::class)->record(
            'v3.test.outbox',
            null,
            $user,
            ['source' => 'clean-room'],
            'v3.test.outbox:'.$account->userId,
        );

        self::assertTrue(AuditEvent::query()->whereKey($audit->id)->where('actor_user_id', $account->userId)->exists());
        self::assertTrue(OutboxMessage::query()->whereKey($outbox->id)->where('idempotency_key', 'v3.test.outbox:'.$account->userId)->exists());
        self::assertSame('v3.test.outbox', $outbox->event_type);
    }
}
