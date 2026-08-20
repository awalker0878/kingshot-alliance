<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;

final readonly class QueueAnnouncementBroadcastRun
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private NotificationDeliveryService $deliveries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        ContentItem $item,
        CarbonImmutable $scheduledFor,
        string $idempotencyKey,
        ?string $scheduleId = null,
    ): bool {
        $run = AnnouncementBroadcastRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'alliance_id' => (string) $alliance->id,
                'content_item_id' => (string) $item->id,
                'schedule_id' => $scheduleId,
                'scheduled_for' => $scheduledFor,
                'status' => BroadcastRunStatus::Queued,
            ],
        );
        if (! $run->wasRecentlyCreated) {
            return false;
        }

        $playerIds = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('player_id')
            ->pluck('player_id')
            ->map(static fn (mixed $playerId): string => (string) $playerId)
            ->unique()
            ->values()
            ->all();
        $recipients = $this->players->byIds($playerIds);
        ksort($recipients);

        $recipientCount = 0;
        $deliveryCount = 0;
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof PlayerReference || $recipient->userId === null) {
                continue;
            }

            $recipientCount++;
            $batch = $this->deliveries->queueEnabledChannelBatch(
                notificationType: 'alliance.announcement',
                recipientUserId: $recipient->userId,
                playerId: $recipient->playerId,
                dueAt: now(),
                idempotencyKey: implode(':', ['alliance-announcement-run', $run->id, $recipient->playerId]),
                subjectType: 'content_item',
                subjectId: (string) $item->id,
                metadata: [
                    'title' => (string) $item->title,
                    'body' => mb_substr(trim((string) ($item->summary ?: $item->body)), 0, 1000),
                    'action_url' => '/alliance/content/'.rawurlencode((string) $item->slug),
                    'alliance_id' => (string) $alliance->id,
                    'content_item_id' => (string) $item->id,
                    'broadcast_run_id' => (string) $run->id,
                ],
            );
            $deliveryCount += $batch->count();
        }

        $run->forceFill([
            'status' => $recipientCount === 0 ? BroadcastRunStatus::Empty : BroadcastRunStatus::Queued,
            'recipient_count' => $recipientCount,
            'delivery_count' => $deliveryCount,
            'queued_at' => now(),
        ])->save();
        $context = [
            'broadcast_run_id' => (string) $run->id,
            'content_item_id' => (string) $item->id,
            'schedule_id' => $scheduleId,
            'scheduled_for' => $scheduledFor->toIso8601String(),
            'recipient_count' => $recipientCount,
            'delivery_count' => $deliveryCount,
        ];
        $this->audit->record('content.broadcast_queued', null, $run, $alliance, $context);
        $this->outbox->record(
            'broadcast.run.queued',
            (string) $alliance->id,
            $run,
            $context,
            'broadcast-run:'.$run->id.':queued',
            'alliance:'.$alliance->id,
        );

        return true;
    }
}
