<?php

declare(strict_types=1);

namespace App\ReadModels\AnnouncementBroadcastManagement\Queries;

use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Communications\Delivery\Queries\AnnouncementDeliverySummaryQuery;

final readonly class AnnouncementBroadcastManagementQuery
{
    public function __construct(private AnnouncementDeliverySummaryQuery $deliverySummaries) {}

    /**
     * @return array{
     *   schedules: array<string, array<string, mixed>>,
     *   runs: array<string, list<array<string, mixed>>>
     * }
     */
    public function forAlliance(string $allianceId): array
    {
        $schedules = [];
        foreach (AnnouncementBroadcastSchedule::query()
            ->where('alliance_id', $allianceId)
            ->orderByDesc('updated_at')
            ->get() as $schedule) {
            $schedules[(string) $schedule->content_item_id] = [
                'id' => (string) $schedule->id,
                'status' => $schedule->status->value,
                'weekdays' => array_values(array_map('intval', $schedule->weekdays)),
                'localTime' => (string) $schedule->local_time,
                'timezone' => (string) $schedule->timezone,
                'nextRunAt' => $schedule->next_run_at?->toIso8601String(),
                'lastRunAt' => $schedule->last_run_at?->toIso8601String(),
                'endsAt' => $schedule->ends_at?->toIso8601String(),
                'cancelledAt' => $schedule->cancelled_at?->toIso8601String(),
            ];
        }

        $runs = AnnouncementBroadcastRun::query()
            ->where('alliance_id', $allianceId)
            ->orderByDesc('scheduled_for')
            ->limit(100)
            ->get();
        $contentByRun = [];
        foreach ($runs as $run) {
            $contentByRun[(string) $run->id] = (string) $run->content_item_id;
        }
        $summaries = $this->deliverySummaries->forRuns($allianceId, $contentByRun);

        $runsByContent = [];
        foreach ($runs as $run) {
            $contentId = (string) $run->content_item_id;
            if (count($runsByContent[$contentId] ?? []) >= 5) {
                continue;
            }

            $summary = $summaries[(string) $run->id];

            $runsByContent[$contentId][] = [
                'id' => (string) $run->id,
                'scheduleId' => $run->schedule_id,
                'scheduledFor' => $run->scheduled_for->toIso8601String(),
                'status' => $run->status->value,
                'recipientCount' => (int) $run->recipient_count,
                'deliveryCount' => (int) $run->delivery_count,
                'skippedCount' => $run->skipped_count,
                'suppressedCount' => $run->suppressed_count,
                'replayedCount' => $run->replayed_count,
                'deliveryCounts' => $summary->deliveryCounts,
                'readCount' => $summary->readCount,
                'retryCandidateCount' => $summary->retryCandidateCount,
                'failedDeliveryIds' => $summary->failedDeliveryIds,
                'queuedAt' => $run->queued_at?->toIso8601String(),
            ];
        }

        return ['schedules' => $schedules, 'runs' => $runsByContent];
    }
}
