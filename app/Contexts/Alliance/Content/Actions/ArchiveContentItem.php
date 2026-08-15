<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
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
            $context = $this->authority->require($actor, $alliance, AlliancePermission::ContentManage);

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
