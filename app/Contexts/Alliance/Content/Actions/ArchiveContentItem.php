<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\DeactivateAnnouncementBroadcastSchedule;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveContentItem
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private DeactivateAnnouncementBroadcastSchedule $deactivateBroadcastSchedule,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $contentItemId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $contentItemId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $item->slug === ContentItem::ALLIANCE_RULES_SLUG) {
                throw ValidationException::withMessages([
                    'content' => 'Alliance Rules cannot be archived through generic Content management.',
                ]);
            }

            $item->forceFill([
                'status' => ContentStatus::Archived,
                'scheduled_for' => null,
                'archived_at' => now(),
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();
            $this->deactivateBroadcastSchedule->handle($item, $context->actor, 'content-archived');

            $this->audit->record('content.archived', $context->actor, $item, $context->alliance, [
                'revision_number' => $item->current_revision_number,
            ]);
            $this->outbox->record('content.archived', (string) $context->alliance->id, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $item->current_revision_number,
            ]);

            return (string) $item->id;
        });
    }
}
