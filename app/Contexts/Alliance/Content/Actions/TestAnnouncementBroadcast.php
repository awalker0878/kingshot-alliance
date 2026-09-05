<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TestAnnouncementBroadcast
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authority,
        private NotificationDeliveryService $deliveries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @return list<string> */
    public function handle(string $allianceId, string $actorPlayerId, string $contentItemId): array
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $contentItemId): array {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
            if ($context->actor->userId === null) {
                throw ValidationException::withMessages(['player' => 'Claim this Governor before sending a test delivery.']);
            }

            $item = ContentItem::query()
                ->whereKey($contentItemId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($item->type !== ContentType::Announcement || $item->status === ContentStatus::Archived) {
                throw ValidationException::withMessages(['content' => 'Only active announcements can be tested.']);
            }

            $receipt = $this->deliveries->queue(new NotificationIntent(
                notificationType: 'alliance.announcement',
                recipientUserId: $context->actor->userId,
                playerId: $context->actor->playerId,
                availableAt: CarbonImmutable::now('UTC'),
                idempotencyKey: 'announcement-test:'.$item->id.':'.$context->actor->playerId.':'.Str::ulid(),
                title: '[Test] '.(string) $item->title,
                body: mb_substr(trim((string) ($item->summary ?: $item->body)), 0, 1000),
                actionUrl: '/alliance/content/'.rawurlencode((string) $item->slug),
                subjectType: 'content_item',
                subjectId: (string) $item->id,
                metadata: [
                    'alliance_id' => $allianceId,
                    'content_item_id' => (string) $item->id,
                    'test_delivery' => true,
                ],
            ));
            $metadata = [
                'content_item_id' => (string) $item->id,
                'channels' => $receipt->channels,
                'delivery_count' => $receipt->count(),
            ];
            $this->audit->record('content.broadcast_test_queued', $context->actor, $item, $allianceId, $metadata);
            $this->outbox->record(
                'content.broadcast_test_queued',
                $allianceId,
                $item,
                $metadata,
                null,
                'alliance:'.$allianceId,
            );

            return $receipt->channels;
        });
    }
}
