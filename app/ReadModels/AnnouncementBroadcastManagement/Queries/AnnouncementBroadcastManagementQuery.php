<?php

declare(strict_types=1);

namespace App\ReadModels\AnnouncementBroadcastManagement\Queries;

use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Queries\ContentManagementQuery;
use App\Contexts\Communications\Delivery\Queries\AnnouncementDeliverySummaryQuery;

/** Composes only the Content history page authorized for the current manager. */
final readonly class AnnouncementBroadcastManagementQuery
{
    public function __construct(private ContentManagementQuery $content, private AnnouncementDeliverySummaryQuery $deliverySummaries) {}

    /** @return array<string,mixed> */
    public function history(string $allianceId, string $playerId, string $contentId, ?string $cursor = null): array
    {
        $result = $this->content->runs($allianceId, $playerId, $contentId, $cursor);
        $page = $result['page']->toArray();
        $contentByRun = [];
        foreach ($result['page']->items as $run) {
            $contentByRun[$run->id] = $run->content_item_id;
        }
        $summaries = $this->deliverySummaries->forRuns($allianceId, $contentByRun);
        $page['items'] = array_map(static function (AnnouncementBroadcastRun $run) use ($summaries): array {
            $summary = $summaries[$run->id];

            return ['id' => $run->id, 'scheduleId' => $run->schedule_id, 'scheduledFor' => $run->scheduled_for->toIso8601String(),
                'status' => $run->status->value, 'recipientCount' => $run->recipient_count,
                'skippedCount' => $run->skipped_count, 'suppressedCount' => $run->suppressed_count, 'replayedCount' => $run->replayed_count,
                'deliveryCount' => $run->delivery_count, 'deliveryCounts' => $summary->deliveryCounts,
                'readCount' => $summary->readCount, 'retryCandidateCount' => $summary->retryCandidateCount,
                'failedDeliveryIds' => $summary->failedDeliveryIds, 'queuedAt' => $run->queued_at?->toIso8601String()];
        }, $result['page']->items);

        return ['page' => $page, 'total' => $result['total']];
    }

    /** @return array<string,mixed>|null */
    public function schedule(?AnnouncementBroadcastSchedule $schedule): ?array
    {
        return $schedule === null ? null : ['id' => (string) $schedule->id, 'status' => $schedule->status->value,
            'timezone' => $schedule->timezone, 'weekdays' => array_values(array_map('intval', $schedule->weekdays ?? [])), 'localTime' => $schedule->local_time,
            'nextRunAt' => $schedule->next_run_at?->toIso8601String(), 'lastRunAt' => $schedule->last_run_at?->toIso8601String(),
            'endsAt' => $schedule->ends_at?->toIso8601String(), 'cancelledAt' => $schedule->cancelled_at?->toIso8601String()];
    }
}
