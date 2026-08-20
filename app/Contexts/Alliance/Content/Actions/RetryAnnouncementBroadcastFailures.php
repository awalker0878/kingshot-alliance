<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Communications\Delivery\Actions\RetryFailedNotificationDeliveries;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryAnnouncementBroadcastFailures
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authority,
        private RetryFailedNotificationDeliveries $retryDeliveries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param non-empty-list<string> $deliveryIds */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $runId,
        array $deliveryIds,
    ): int {
        if (count($deliveryIds) > 50) {
            throw ValidationException::withMessages(['delivery_ids' => 'Retry at most 50 failed deliveries at once.']);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $runId, $deliveryIds): int {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
            $run = AnnouncementBroadcastRun::query()
                ->whereKey($runId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            $retried = $this->retryDeliveries->handle(
                $context->actor,
                $allianceId,
                $deliveryIds,
                'alliance.announcement',
                'content_item',
                (string) $run->content_item_id,
                ['broadcast_run_id' => (string) $run->id],
            );
            if ($retried === []) {
                throw ValidationException::withMessages([
                    'delivery_ids' => 'No selected failures are currently eligible for retry.',
                ]);
            }

            $metadata = [
                'broadcast_run_id' => (string) $run->id,
                'content_item_id' => (string) $run->content_item_id,
                'delivery_ids' => $retried,
                'retried' => count($retried),
            ];
            $this->audit->record('content.broadcast_failures_retry_queued', $context->actor, $run, $allianceId, $metadata);
            $this->outbox->record(
                'content.broadcast_failures_retry_queued',
                $allianceId,
                $run,
                $metadata,
                null,
                'alliance:'.$allianceId,
            );

            return count($retried);
        });
    }
}
