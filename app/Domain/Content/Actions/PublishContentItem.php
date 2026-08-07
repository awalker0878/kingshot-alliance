<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentOutbox;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class PublishContentItem
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $contentItemId, ?Carbon $scheduledFor = null): ContentItem
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $contentItemId, $scheduledFor): ContentItem {
            $item = ContentItem::query()
                ->where('id', $contentItemId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isScheduled = $scheduledFor instanceof Carbon && $scheduledFor->isFuture();
            $status = $isScheduled ? ContentStatus::Scheduled : ContentStatus::Published;

            $item->forceFill([
                'status' => $status,
                'scheduled_for' => $isScheduled ? $scheduledFor->utc() : null,
                'published_at' => $isScheduled ? null : now(),
                'archived_at' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $event = $isScheduled ? 'content.scheduled' : 'content.published';
            $this->audit->record($event, $actor, $item, $alliance, [
                'revision_number' => $item->current_revision_number,
                'scheduled_for' => $item->scheduled_for?->toIso8601String(),
            ]);
            $this->outbox->record($event, $alliance, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $item->current_revision_number,
                'scheduled_for' => $item->scheduled_for?->toIso8601String(),
            ]);

            return $item->refresh();
        });
    }
}
