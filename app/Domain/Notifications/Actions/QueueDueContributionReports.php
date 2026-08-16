<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Contexts\Intelligence\Contributions\Models\ContributionReportRun;
use App\Contexts\Intelligence\Contributions\Models\ContributionReportSchedule;
use App\Shared\Messaging\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class QueueDueContributionReports
{
    public function handle(int $limit = 50): int
    {
        $queued = 0;
        $limit = max(1, min($limit, 250));

        for ($index = 0; $index < $limit; $index++) {
            $result = DB::transaction(function (): ?bool {
                /** @var Builder<ContributionReportSchedule> $query */
                $query = ContributionReportSchedule::query()
                    ->where('is_enabled', true)
                    ->where('next_due_at', '<=', now())
                    ->orderBy('next_due_at')
                    ->orderBy('id');

                if (DB::getDriverName() === 'pgsql') {
                    $query->lock('for update skip locked');
                } else {
                    $query->lockForUpdate();
                }

                $schedule = $query->first();

                if (! $schedule instanceof ContributionReportSchedule) {
                    return null;
                }

                $dueAt = CarbonImmutable::parse($schedule->next_due_at->toIso8601String())->utc();
                $idempotencyKey = hash('sha256', implode('|', [
                    $schedule->id,
                    $dueAt->toIso8601String(),
                    $schedule->report_version,
                ]));

                $run = ContributionReportRun::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'alliance_id' => $schedule->alliance_id,
                        'schedule_id' => $schedule->id,
                        'recipient_player_id' => $schedule->recipient_player_id,
                        'format' => 'scheduled',
                        'status' => 'queued',
                        'report_version' => $schedule->report_version,
                        'filters' => ['as_of' => $dueAt->toIso8601String()],
                        'queued_at' => now(),
                    ],
                );

                OutboxMessage::query()->firstOrCreate(
                    ['idempotency_key' => 'contribution.report.requested:'.$idempotencyKey],
                    [
                        'alliance_id' => $schedule->alliance_id,
                        'event_type' => 'contribution.report.requested',
                        'aggregate_type' => $run->getMorphClass(),
                        'aggregate_id' => $run->id,
                        'payload' => [
                            'alliance_id' => $schedule->alliance_id,
                            'report_run_id' => $run->id,
                            'schedule_id' => $schedule->id,
                            'recipient_player_id' => $schedule->recipient_player_id,
                            'report_version' => $schedule->report_version,
                            'as_of' => $dueAt->toIso8601String(),
                        ],
                        'occurred_at' => now(),
                        'available_at' => now(),
                        'attempts' => 0,
                    ],
                );

                $localDue = $dueAt->setTimezone($schedule->timezone);
                $nextLocal = match ($schedule->cadence) {
                    'daily' => $localDue->addDay(),
                    'weekly' => $localDue->addWeek(),
                    'monthly' => $localDue->addMonthNoOverflow(),
                    default => $localDue->addDay(),
                };

                $schedule->forceFill([
                    'last_queued_at' => now(),
                    'next_due_at' => $nextLocal->utc(),
                ])->save();

                return $run->wasRecentlyCreated;
            });

            if ($result === null) {
                break;
            }

            if ($result) {
                $queued++;
            }
        }

        return $queued;
    }
}
