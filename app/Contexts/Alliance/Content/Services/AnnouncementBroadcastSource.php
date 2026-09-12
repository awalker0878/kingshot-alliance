<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\ContentQuery;

/** Current source facts, shared by enqueue and external-delivery authorization. */
final readonly class AnnouncementBroadcastSource
{
    public function __construct(private ContentQuery $content) {}

    public function published(ContentItem $item): bool
    {
        return $item->notify_members
            && $this->content->publishedAnnouncementExists((string) $item->alliance_id, (string) $item->id);
    }

    public function allows(AnnouncementBroadcastRun $run, ?ContentItem $item, ?AnnouncementBroadcastSchedule $schedule): bool
    {
        if ($item === null || $run->status === BroadcastRunStatus::Cancelled || $run->scheduled_for->isFuture()
            || $run->content_item_id !== (string) $item->id || $run->alliance_id !== (string) $item->alliance_id
            || $run->content_revision_number !== $item->current_revision_number || ! $this->published($item)) {
            return false;
        }
        if ($run->schedule_id === null) {
            return $run->schedule_generation === null;
        }

        return $schedule !== null && (string) $schedule->id === $run->schedule_id
            && $schedule->alliance_id === $run->alliance_id && $schedule->content_item_id === $run->content_item_id
            && $schedule->generation === $run->schedule_generation
            && $schedule->status !== BroadcastScheduleStatus::Cancelled
            && ($schedule->ends_at === null || $run->scheduled_for->lessThanOrEqualTo($schedule->ends_at));
    }
}
