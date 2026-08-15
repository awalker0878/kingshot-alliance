<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class PublishContentItem
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $contentItemId, ?Carbon $scheduledFor = null): ContentItem
    {
        return DB::transaction(function () use ($alliance, $actor, $contentItemId, $scheduledFor): ContentItem {
            $context = $this->authority->require($actor, $alliance, AlliancePermission::ContentManage);

            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isScheduled = $scheduledFor instanceof Carbon && $scheduledFor->isFuture();
            $status = $isScheduled ? ContentStatus::Scheduled : ContentStatus::Published;

            $item->forceFill([
                'status' => $status,
                'scheduled_for' => $isScheduled ? $scheduledFor->utc() : null,
                'published_at' => $isScheduled ? null : now(),
                'archived_at' => null,
                'updated_by_player_id' => $context->actor->id,
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

            return $item->refresh();
        });
    }
}
