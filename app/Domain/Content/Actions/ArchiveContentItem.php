<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveContentItem
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $contentItemId): ContentItem
    {
        return DB::transaction(function () use ($alliance, $actor, $contentItemId): ContentItem {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContentManage);

            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $item->forceFill([
                'status' => ContentStatus::Archived,
                'scheduled_for' => null,
                'archived_at' => now(),
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $this->audit->record('content.archived', $context->actor, $item, $context->alliance, [
                'revision_number' => $item->current_revision_number,
            ]);
            $this->outbox->record('content.archived', (string) $context->alliance->id, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $item->current_revision_number,
            ]);

            return $item->refresh();
        });
    }
}
