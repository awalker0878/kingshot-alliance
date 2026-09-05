<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
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
        string $allianceId,
        string $contentItemId,
        CarbonImmutable $scheduledFor,
        string $idempotencyKey,
        ?string $scheduleId = null,
    ): bool {
        $item = ContentItem::query()
            ->whereKey($contentItemId)
            ->where('alliance_id', $allianceId)
            ->firstOrFail();
        $run = AnnouncementBroadcastRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'alliance_id' => $allianceId,
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
            ->where('alliance_id', $allianceId)
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
            $receipt = $this->deliveries->queue(new NotificationIntent(
                notificationType: 'alliance.announcement',
                recipientUserId: $recipient->userId,
                playerId: $recipient->playerId,
                availableAt: CarbonImmutable::now('UTC'),
                idempotencyKey: implode(':', ['alliance-announcement-run', $run->id, $recipient->playerId]),
                title: (string) $item->title,
                body: mb_substr(trim((string) ($item->summary ?: $item->body)), 0, 1000),
                actionUrl: '/alliance/content/'.rawurlencode((string) $item->slug),
                subjectType: 'content_item',
                subjectId: (string) $item->id,
                metadata: [
                    'alliance_id' => $allianceId,
                    'content_item_id' => (string) $item->id,
                    'broadcast_run_id' => (string) $run->id,
                ],
            ));
            $deliveryCount += $receipt->count();
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
        $this->audit->record('content.broadcast_queued', null, $run, $allianceId, $context);
        $this->outbox->record(
            'broadcast.run.queued',
            $allianceId,
            $run,
            $context,
            'broadcast-run:'.$run->id.':queued',
            'alliance:'.$allianceId,
        );

        return true;
    }
}
