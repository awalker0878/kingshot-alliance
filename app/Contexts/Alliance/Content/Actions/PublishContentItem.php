<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PublishContentItem
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $contentItemId, ?Carbon $scheduledFor = null): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $contentItemId, $scheduledFor): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $item->slug === ContentItem::ALLIANCE_RULES_SLUG) {
                throw ValidationException::withMessages([
                    'content' => 'Alliance Rules are published only through the dedicated Alliance Rules workflow.',
                ]);
            }

            if (! $item->provenanceIsComplete()) {
                throw ValidationException::withMessages([
                    'provenance' => 'Knowledge content requires a source label and review date before publication.',
                ]);
            }

            $isScheduled = $scheduledFor instanceof Carbon && $scheduledFor->isFuture();
            $status = $isScheduled ? ContentStatus::Scheduled : ContentStatus::Published;

            $item->forceFill([
                'status' => $status,
                'scheduled_for' => $isScheduled ? $scheduledFor->utc() : null,
                'published_at' => $isScheduled ? null : now(),
                'archived_at' => null,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $event = $isScheduled ? 'content.scheduled' : 'content.published';
            $this->audit->record($event, $context->actor, $item, $context->alliance, [
                'revision_number' => $item->current_revision_number,
                'scheduled_for' => $item->scheduled_for?->toIso8601String(),
            ]);
            $this->outbox->record($event, (string) $context->alliance->id, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $item->current_revision_number,
                'scheduled_for' => $item->scheduled_for?->toIso8601String(),
            ]);

            return (string) $item->id;
        });
    }
}
