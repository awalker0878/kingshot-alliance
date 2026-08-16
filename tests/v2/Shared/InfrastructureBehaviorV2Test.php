<?php

declare(strict_types=1);

namespace Tests\v2\Shared;

use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class InfrastructureBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_audit_and_outbox_are_neutral_durable_infrastructure(): void
    {
        $user = (new ScenarioFactory)->user();
        $audit = app(AuditRecorder::class)->record('v2.test.audit', $user, $user, null, ['source' => 'clean-room']);
        $outbox = app(OutboxRecorder::class)->record(
            'v2.test.outbox',
            null,
            $user,
            ['source' => 'clean-room'],
            'v2.test.outbox:'.$user->id,
        );

        self::assertTrue(AuditEvent::query()->whereKey($audit->id)->where('actor_user_id', $user->id)->exists());
        self::assertTrue(OutboxMessage::query()->whereKey($outbox->id)->where('idempotency_key', 'v2.test.outbox:'.$user->id)->exists());
        self::assertSame('v2.test.outbox', $outbox->event_type);
    }
}
