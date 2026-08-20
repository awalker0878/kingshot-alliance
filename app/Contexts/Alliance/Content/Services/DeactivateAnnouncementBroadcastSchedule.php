<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;

final readonly class DeactivateAnnouncementBroadcastSchedule
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(ContentItem $item, AuditActor $actor, string $reason): void
    {
        $schedule = AnnouncementBroadcastSchedule::query()
            ->where('alliance_id', $item->alliance_id)
            ->where('content_item_id', $item->id)
            ->where('status', BroadcastScheduleStatus::Active->value)
            ->lockForUpdate()
            ->first();
        if (! $schedule instanceof AnnouncementBroadcastSchedule) {
            return;
        }

        $schedule->forceFill([
            'status' => BroadcastScheduleStatus::Cancelled,
            'next_run_at' => null,
            'cancelled_at' => now(),
        ])->save();
        $metadata = [
            'schedule_id' => (string) $schedule->id,
            'content_item_id' => (string) $item->id,
            'reason' => $reason,
        ];
        $this->audit->record(
            'content.broadcast_schedule_cancelled',
            $actor,
            $schedule,
            (string) $item->alliance_id,
            $metadata,
        );
        $this->outbox->record(
            'broadcast.schedule.cancelled',
            (string) $item->alliance_id,
            $schedule,
            $metadata,
            'broadcast-schedule:'.$schedule->id.':cancelled',
            'alliance:'.$item->alliance_id,
        );
    }
}
