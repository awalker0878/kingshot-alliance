<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AllianceNoticeReaction;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveNoticeReaction
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $contentItemId): bool
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $contentItemId): bool {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $item = $this->lockReactableNotice((string) $context->alliance->id, $contentItemId);

            $existing = AllianceNoticeReaction::query()
                ->where('content_item_id', $item->id)
                ->where('player_id', $context->actor->playerId)
                ->lockForUpdate()
                ->first();

            if (! $existing instanceof AllianceNoticeReaction) {
                return false;
            }

            $previous = $existing->reaction->value;
            $existing->delete();

            $this->audit->record(
                'content.notice-reaction.removed',
                $context->actor,
                null,
                $context->alliance,
                [
                    'content_item_id' => (string) $item->id,
                    'previous_reaction' => $previous,
                ],
            );

            return true;
        });
    }

    private function lockReactableNotice(string $allianceId, string $contentItemId): ContentItem
    {
        return ContentItem::query()
            ->whereKey($contentItemId)
            ->where('alliance_id', $allianceId)
            ->where('type', ContentType::Announcement->value)
            ->where('status', ContentStatus::Published->value)
            ->whereIn('visibility', [ContentVisibility::Public->value, ContentVisibility::Members->value])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->firstOrFail();
    }
}
