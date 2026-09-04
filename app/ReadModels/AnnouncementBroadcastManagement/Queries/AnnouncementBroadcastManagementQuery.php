<?php

declare(strict_types=1);

namespace App\ReadModels\AnnouncementBroadcastManagement\Queries;

use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;

final class AnnouncementBroadcastManagementQuery
{
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
        $runIds = array_values($runs
            ->map(static fn (AnnouncementBroadcastRun $run): string => (string) $run->id)
            ->values()
            ->all());
        $contentIds = array_values($runs
            ->map(static fn (AnnouncementBroadcastRun $run): string => (string) $run->content_item_id)
            ->unique()
            ->values()
            ->all());
        /** @var array<string,list<NotificationDelivery>> $deliveriesByRun */
        $deliveriesByRun = [];
        /** @var array<string,int> $readByRun */
        $readByRun = [];

        if ($runIds !== [] && $contentIds !== []) {
            $messages = NotificationMessage::query()
                ->where('notification_type', 'alliance.announcement')
                ->where('subject_type', 'content_item')
                ->whereIn('subject_id', $contentIds)
                ->orderByDesc('created_at')
                ->limit(1000)
                ->get();
            /** @var array<string,string> $runByMessage */
            $runByMessage = [];

            foreach ($messages as $message) {
                $metadata = is_array($message->metadata) ? $message->metadata : [];
                $runId = isset($metadata['broadcast_run_id']) ? (string) $metadata['broadcast_run_id'] : '';
                if ($runId === '' || ! in_array($runId, $runIds, true)) {
                    continue;
                }

                $runByMessage[(string) $message->id] = $runId;
                if ($message->read_at !== null) {
                    $readByRun[$runId] = ($readByRun[$runId] ?? 0) + 1;
                }
            }

            if ($runByMessage !== []) {
                $deliveries = NotificationDelivery::query()
                    ->whereIn('notification_message_id', array_keys($runByMessage))
                    ->orderByDesc('created_at')
                    ->limit(5000)
                    ->get();
                foreach ($deliveries as $delivery) {
                    $runId = $runByMessage[(string) $delivery->notification_message_id] ?? null;
                    if ($runId !== null) {
                        $deliveriesByRun[$runId][] = $delivery;
                    }
                }
            }
        }

        $runsByContent = [];
        foreach ($runs as $run) {
            $contentId = (string) $run->content_item_id;
            if (count($runsByContent[$contentId] ?? []) >= 5) {
                continue;
            }

            $deliveryCounts = array_fill_keys(array_map(
                static fn (DeliveryStatus $status): string => $status->value,
                DeliveryStatus::cases(),
            ), 0);
            $failedDeliveryIds = [];
            foreach ($deliveriesByRun[(string) $run->id] ?? [] as $delivery) {
                $deliveryCounts[$delivery->status->value]++;
                if ($delivery->status === DeliveryStatus::Failed
                    && $delivery->attempt_count < $delivery->max_attempts
                    && count($failedDeliveryIds) < 50) {
                    $failedDeliveryIds[] = (string) $delivery->id;
                }
            }

            $runsByContent[$contentId][] = [
                'id' => (string) $run->id,
                'scheduleId' => $run->schedule_id,
                'scheduledFor' => $run->scheduled_for->toIso8601String(),
                'status' => $run->status->value,
                'recipientCount' => (int) $run->recipient_count,
                'deliveryCount' => (int) $run->delivery_count,
                'deliveryCounts' => $deliveryCounts,
                'readCount' => $readByRun[(string) $run->id] ?? 0,
                'failedDeliveryIds' => $failedDeliveryIds,
                'queuedAt' => $run->queued_at?->toIso8601String(),
            ];
        }

        return ['schedules' => $schedules, 'runs' => $runsByContent];
    }
}
