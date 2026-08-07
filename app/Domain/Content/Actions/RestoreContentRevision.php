<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Services\ContentOutbox;
use App\Domain\Content\Services\ContentRevisionWriter;

use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RestoreContentRevision
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ContentRevisionWriter $revisions,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $contentItemId, string $revisionId): ContentItem
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $contentItemId, $revisionId): ContentItem {
            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $revision = ContentRevision::query()
                ->where('id', $revisionId)
                ->where('content_item_id', $item->id)
                ->where('alliance_id', $alliance->id)
                ->firstOrFail();

            $restoredFrom = $revision->revision_number;
            $item->forceFill([
                'category_id' => $revision->category_id,
                'type' => $revision->type,
                'visibility' => $revision->visibility,
                'status' => ContentStatus::Draft,
                'title' => $revision->title,
                'summary' => $revision->summary,
                'body' => $revision->body,
                'locale' => $revision->locale,
                'sort_order' => $revision->sort_order,
                'current_revision_number' => (int) $item->current_revision_number + 1,
                'scheduled_for' => null,
                'published_at' => null,
                'archived_at' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $newRevision = $this->revisions->write($item, $actor);

            $this->audit->record('content.revision_restored', $actor, $item, $alliance, [
                'restored_from_revision' => $restoredFrom,
                'revision_number' => $newRevision->revision_number,
            ]);
            $this->outbox->record('content.revision_restored', $alliance, $item, [
                'content_item_id' => $item->id,
                'restored_from_revision' => $restoredFrom,
                'revision_number' => $newRevision->revision_number,
            ]);

            return $item->refresh();
        });
    }
}
