<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CancelAnnouncementBroadcastSchedule
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $scheduleId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $scheduleId): void {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
            $schedule = AnnouncementBroadcastSchedule::query()
                ->whereKey($scheduleId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($schedule->status === BroadcastScheduleStatus::Cancelled) {
                return;
            }

            $schedule->forceFill([
                'status' => BroadcastScheduleStatus::Cancelled,
                'next_run_at' => null,
                'cancelled_at' => now(),
            ])->save();
            $metadata = [
                'schedule_id' => (string) $schedule->id,
                'content_item_id' => $schedule->content_item_id,
                'reason' => 'manager-cancelled',
            ];
            $this->audit->record('content.broadcast_schedule_cancelled', $context->actor, $schedule, $context->alliance, $metadata);
            $this->outbox->record(
                'broadcast.schedule.cancelled',
                $allianceId,
                $schedule,
                $metadata,
                'broadcast-schedule:'.$schedule->id.':cancelled',
                'alliance:'.$allianceId,
            );
        });
    }
}
