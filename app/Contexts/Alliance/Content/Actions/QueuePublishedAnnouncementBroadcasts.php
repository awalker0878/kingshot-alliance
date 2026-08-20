<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\NextBroadcastOccurrence;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class QueuePublishedAnnouncementBroadcasts
{
    public function __construct(
        private QueueAnnouncementBroadcastRun $queueRun,
        private NextBroadcastOccurrence $nextOccurrence,
    ) {}

    public function handle(int $limit = 25): int
    {
        $limit = max(1, min(100, $limit));
        $queued = $this->queueOneOffs($limit);

        $remaining = $limit - $queued;
        if ($remaining <= 0) {
            return $queued;
        }

        return $queued + $this->queueRecurring($remaining);
    }

    private function queueOneOffs(int $limit): int
    {
        $ids = ContentItem::query()
            ->where('type', ContentType::Announcement->value)
            ->where('status', ContentStatus::Published->value)
            ->where('notify_members', true)
            ->whereNull('broadcasted_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $queued = 0;

        foreach ($ids as $id) {
            $created = DB::transaction(function () use ($id): bool {
                $candidate = ContentItem::query()->select(['id', 'alliance_id'])->whereKey($id)->first();
                if (! $candidate instanceof ContentItem) {
                    return false;
                }

                $alliance = Alliance::query()
                    ->whereKey($candidate->alliance_id)
                    ->where('status', AllianceStatus::Active->value)
                    ->sharedLock()
                    ->first();
                if (! $alliance instanceof Alliance) {
                    return false;
                }

                $item = ContentItem::query()
                    ->whereKey($id)
                    ->where('alliance_id', $alliance->id)
                    ->where('type', ContentType::Announcement->value)
                    ->where('status', ContentStatus::Published->value)
                    ->where('notify_members', true)
                    ->whereNull('broadcasted_at')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->lockForUpdate()
                    ->first();
                if (! $item instanceof ContentItem || $item->published_at === null) {
                    return false;
                }

                $created = $this->queueRun->handle(
                    (string) $alliance->id,
                    (string) $item->id,
                    CarbonImmutable::instance($item->published_at),
                    'announcement-one-off:'.$item->id,
                );
                $item->forceFill(['broadcasted_at' => now()])->save();

                return $created;
            });

            if ($created) {
                $queued++;
            }
        }

        return $queued;
    }

    private function queueRecurring(int $limit): int
    {
        $ids = AnnouncementBroadcastSchedule::query()
            ->where('status', BroadcastScheduleStatus::Active->value)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $queued = 0;

        foreach ($ids as $id) {
            $created = DB::transaction(function () use ($id): bool {
                $candidate = AnnouncementBroadcastSchedule::query()
                    ->select(['id', 'alliance_id'])
                    ->whereKey($id)
                    ->first();
                if (! $candidate instanceof AnnouncementBroadcastSchedule) {
                    return false;
                }

                $alliance = Alliance::query()
                    ->whereKey($candidate->alliance_id)
                    ->where('status', AllianceStatus::Active->value)
                    ->sharedLock()
                    ->first();
                if (! $alliance instanceof Alliance) {
                    return false;
                }

                $schedule = AnnouncementBroadcastSchedule::query()
                    ->whereKey($id)
                    ->where('alliance_id', $alliance->id)
                    ->where('status', BroadcastScheduleStatus::Active->value)
                    ->whereNotNull('next_run_at')
                    ->where('next_run_at', '<=', now())
                    ->lockForUpdate()
                    ->first();
                if (! $schedule instanceof AnnouncementBroadcastSchedule || $schedule->next_run_at === null) {
                    return false;
                }
                if ($schedule->ends_at?->isPast() === true) {
                    $schedule->forceFill([
                        'status' => BroadcastScheduleStatus::Completed,
                        'next_run_at' => null,
                    ])->save();

                    return false;
                }

                $item = ContentItem::query()
                    ->whereKey($schedule->content_item_id)
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->first();
                if (! $item instanceof ContentItem
                    || $item->type !== ContentType::Announcement
                    || $item->status !== ContentStatus::Published
                    || ! $item->notify_members) {
                    $schedule->forceFill([
                        'status' => BroadcastScheduleStatus::Completed,
                        'next_run_at' => null,
                    ])->save();

                    return false;
                }

                $scheduledFor = $schedule->next_run_at;
                $created = $this->queueRun->handle(
                    (string) $alliance->id,
                    (string) $item->id,
                    $scheduledFor,
                    hash('sha256', implode('|', [
                        'announcement-recurring',
                        (string) $schedule->id,
                        $scheduledFor->toIso8601String(),
                    ])),
                    (string) $schedule->id,
                );
                $next = $this->nextOccurrence->calculate(
                    $schedule->weekdays,
                    $schedule->local_time,
                    $schedule->timezone,
                    $scheduledFor,
                    $schedule->ends_at,
                );
                $schedule->forceFill([
                    'status' => $next === null
                        ? BroadcastScheduleStatus::Completed
                        : BroadcastScheduleStatus::Active,
                    'last_run_at' => $scheduledFor,
                    'next_run_at' => $next,
                ])->save();

                return $created;
            });

            if ($created) {
                $queued++;
            }
        }

        return $queued;
    }
}
