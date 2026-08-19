<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class QueuePublishedAnnouncementBroadcasts
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private NotificationDeliveryService $deliveries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 25): int
    {
        $ids = ContentItem::query()
            ->where('type', ContentType::Announcement->value)
            ->where('status', ContentStatus::Published->value)
            ->where('notify_members', true)
            ->whereNull('broadcasted_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->limit(max(1, min(100, $limit)))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $broadcasts = 0;
        foreach ($ids as $id) {
            $queued = DB::transaction(function () use ($id): bool {
                $candidate = ContentItem::query()
                    ->select(['id', 'alliance_id'])
                    ->whereKey($id)
                    ->first();
                if (! $candidate instanceof ContentItem) {
                    return false;
                }

                $alliance = Alliance::query()
                    ->whereKey($candidate->alliance_id)
                    ->where('status', AllianceStatus::Active->value)
                    ->sharedLock()
                    ->first();
                if (! $alliance instanceof Alliance) {
                    return false;
                }

                $item = ContentItem::query()
                    ->whereKey($id)
                    ->where('alliance_id', $alliance->id)
                    ->where('type', ContentType::Announcement->value)
                    ->where('status', ContentStatus::Published->value)
                    ->where('notify_members', true)
                    ->whereNull('broadcasted_at')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->lockForUpdate()
                    ->first();
                if (! $item instanceof ContentItem) {
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
                    $created = $this->deliveries->queueEnabledChannels(
                        notificationType: 'alliance.announcement',
                        recipientUserId: $recipient->userId,
                        playerId: $recipient->playerId,
                        dueAt: now(),
                        idempotencyKey: implode(':', ['alliance-announcement', $item->id, $recipient->playerId]),
                        subjectType: 'content_item',
                        subjectId: (string) $item->id,
                        metadata: [
                            'title' => (string) $item->title,
                            'body' => mb_substr(trim((string) ($item->summary ?: $item->body)), 0, 1000),
                            'action_url' => '/alliance/content/'.rawurlencode((string) $item->slug),
                            'alliance_id' => (string) $alliance->id,
                            'content_item_id' => (string) $item->id,
                        ],
                    );

                    foreach ($created as $delivery) {
                        if ($delivery->wasRecentlyCreated) {
                            $deliveryCount++;
                        }
                    }
                }

                $item->forceFill(['broadcasted_at' => now()])->save();
                $context = [
                    'recipient_count' => $recipientCount,
                    'delivery_count' => $deliveryCount,
                ];
                $this->audit->record('content.broadcast_queued', null, $item, $alliance, $context);
                $this->outbox->record(
                    'content.broadcast_queued',
                    (string) $alliance->id,
                    $item,
                    ['content_item_id' => (string) $item->id, ...$context],
                );

                return true;
            });

            if ($queued) {
                $broadcasts++;
            }
        }

        return $broadcasts;
    }
}
