<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Services\ContentRevisionWriter;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RestoreContentRevision
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private ContentRevisionWriter $revisions,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $contentItemId, string $revisionId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $contentItemId, $revisionId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $item->slug === ContentItem::ALLIANCE_RULES_SLUG) {
                throw ValidationException::withMessages([
                    'content' => 'Alliance Rules revisions are managed only through the dedicated Alliance Rules workflow.',
                ]);
            }

            $revision = ContentRevision::query()
                ->where('id', $revisionId)
                ->where('content_item_id', $item->id)
                ->where('alliance_id', $context->alliance->id)
                ->firstOrFail();

            if ($revision->category_id !== null) {
                $category = ContentCategory::query()
                    ->whereKey($revision->category_id)
                    ->where('alliance_id', $context->alliance->id)
                    ->sharedLock()
                    ->first();

                if (! $category instanceof ContentCategory) {
                    throw ValidationException::withMessages([
                        'revision' => 'This revision references a category that no longer exists. Choose a current category before restoring it.',
                    ]);
                }
            }

            $restoredFrom = $revision->revision_number;
            $item->forceFill([
                'category_id' => $revision->category_id,
                'type' => $revision->type,
                'visibility' => $revision->visibility,
                'status' => ContentStatus::Draft,
                'title' => $revision->title,
                'summary' => $revision->summary,
                'body' => $revision->body,
                'notify_members' => $revision->notify_members,
                'source_label' => $revision->source_label,
                'source_url' => $revision->source_url,
                'game_version' => $revision->game_version,
                'reviewed_at' => $revision->reviewed_at,
                'context_links' => $revision->context_links,
                'locale' => $revision->locale,
                'sort_order' => $revision->sort_order,
                'current_revision_number' => (int) $item->current_revision_number + 1,
                'scheduled_for' => null,
                'published_at' => null,
                'broadcasted_at' => null,
                'archived_at' => null,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $newRevision = $this->revisions->write($item, $context->actor);

            $this->audit->record('content.revision_restored', $context->actor, $item, $context->alliance, [
                'restored_from_revision' => $restoredFrom,
                'revision_number' => $newRevision->revision_number,
            ]);
            $this->outbox->record('content.revision_restored', (string) $context->alliance->id, $item, [
                'content_item_id' => $item->id,
                'restored_from_revision' => $restoredFrom,
                'revision_number' => $newRevision->revision_number,
            ]);

            return (string) $item->id;
        });
    }
}
