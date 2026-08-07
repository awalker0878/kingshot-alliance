<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveContentItem
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $contentItemId): ContentItem
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $contentItemId): ContentItem {
            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $item->forceFill([
                'status' => ContentStatus::Archived,
                'scheduled_for' => null,
                'archived_at' => now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('content.archived', $actor, $item, $alliance, [
                'revision_number' => $item->current_revision_number,
            ]);
            $this->outbox->record('content.archived', $alliance, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $item->current_revision_number,
            ]);

            return $item->refresh();
        });
    }
}
