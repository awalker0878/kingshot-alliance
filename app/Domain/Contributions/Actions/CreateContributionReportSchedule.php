<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Models\ContributionReportSchedule;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CreateContributionReportSchedule
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        Player $recipient,
        string $name,
        string $cadence,
        string $timezone,
        CarbonImmutable $nextDueAt,
    ): ContributionReportSchedule {
        if ((string) $recipient->current_kingdom_id !== (string) $alliance->kingdom_id) {
            throw new InvalidArgumentException('Scheduled report recipient must belong to the Alliance Kingdom.');
        }

        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Unsupported scheduled report cadence.');
        }

        $schedule = ContributionReportSchedule::query()->create([
            'alliance_id' => $alliance->id,
            'recipient_player_id' => $recipient->id,
            'name' => $name,
            'cadence' => $cadence,
            'timezone' => $timezone,
            'next_due_at' => $nextDueAt->utc(),
            'report_version' => 'phase5.v1',
            'is_enabled' => true,
            'created_by_player_id' => $actor->id,
        ]);

        $this->audit->record('contribution.report-schedule.created', $actor, $schedule, $alliance, [
            'cadence' => $cadence,
            'recipient_player_id' => $recipient->id,
        ]);
        $this->outbox->record('contribution.report-schedule.created', $alliance->id, $schedule, [
            'schedule_id' => $schedule->id,
        ]);

        return $schedule;
    }
}
