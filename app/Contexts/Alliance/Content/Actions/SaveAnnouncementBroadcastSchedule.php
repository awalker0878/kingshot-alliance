<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\NextBroadcastOccurrence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveAnnouncementBroadcastSchedule
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authority,
        private NextBroadcastOccurrence $nextOccurrence,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param non-empty-list<int> $weekdays */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $contentItemId,
        array $weekdays,
        string $localTime,
        string $timezone,
        ?string $endsAt = null,
    ): string {
        return DB::transaction(function () use (
            $allianceId,
            $actorPlayerId,
            $contentItemId,
            $weekdays,
            $localTime,
            $timezone,
            $endsAt,
        ): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
            $item = ContentItem::query()
                ->whereKey($contentItemId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($item->type !== ContentType::Announcement
                || $item->status !== ContentStatus::Published
                || ! $item->notify_members) {
                throw ValidationException::withMessages([
                    'content' => 'Publish a member-notifying announcement before adding recurrence.',
                ]);
            }

            $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
            sort($weekdays);
            if ($weekdays === [] || array_diff($weekdays, range(1, 7)) !== []) {
                throw ValidationException::withMessages(['weekdays' => 'Choose one or more valid recurring days.']);
            }
            if (! in_array($timezone, timezone_identifiers_list(), true)) {
                throw ValidationException::withMessages(['timezone' => 'Choose a valid IANA time zone.']);
            }

            $end = $endsAt === null ? null : CarbonImmutable::parse($endsAt)->utc();
            $next = $this->nextOccurrence->calculate($weekdays, $localTime, $timezone, endsAt: $end);
            if ($next === null) {
                throw ValidationException::withMessages(['ends_at' => 'The recurrence must include a future delivery.']);
            }

            $schedule = AnnouncementBroadcastSchedule::query()->updateOrCreate(
                ['alliance_id' => $allianceId, 'content_item_id' => $contentItemId],
                [
                    'created_by_player_id' => $context->actor->playerId,
                    'timezone' => $timezone,
                    'weekdays' => $weekdays,
                    'local_time' => $localTime,
                    'status' => BroadcastScheduleStatus::Active,
                    'next_run_at' => $next,
                    'ends_at' => $end,
                    'cancelled_at' => null,
                ],
            );
            $metadata = [
                'content_item_id' => $contentItemId,
                'schedule_id' => (string) $schedule->id,
                'timezone' => $timezone,
                'weekdays' => $weekdays,
                'local_time' => $localTime,
                'next_run_at' => $next->toIso8601String(),
                'ends_at' => $end?->toIso8601String(),
            ];
            $this->audit->record('content.broadcast_schedule_saved', $context->actor, $schedule, $context->alliance, $metadata);
            $this->outbox->record(
                'broadcast.schedule.updated',
                $allianceId,
                $schedule,
                $metadata,
                null,
                'alliance:'.$allianceId,
            );

            return (string) $schedule->id;
        });
    }
}
