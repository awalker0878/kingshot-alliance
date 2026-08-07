<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Models\ContributionReportSchedule;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\AllianceMembership;
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
        User $actor,
        Alliance $alliance,
        AllianceMembership $recipient,
        string $name,
        string $cadence,
        string $timezone,
        CarbonImmutable $nextDueAt,
    ): ContributionReportSchedule {
        if ($recipient->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Scheduled report recipient must belong to the active alliance.');
        }

        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Unsupported scheduled report cadence.');
        }

        $schedule = ContributionReportSchedule::query()->create([
            'alliance_id' => $alliance->id,
            'recipient_membership_id' => $recipient->id,
            'name' => $name,
            'cadence' => $cadence,
            'timezone' => $timezone,
            'next_due_at' => $nextDueAt->utc(),
            'report_version' => 'phase5.v1',
            'is_enabled' => true,
            'created_by_user_id' => $actor->id,
        ]);

        $this->audit->record('contribution.report-schedule.created', $actor, $schedule, $alliance, [
            'cadence' => $cadence,
            'recipient_membership_id' => $recipient->id,
        ]);
        $this->outbox->record('contribution.report-schedule.created', $alliance->id, $schedule, [
            'schedule_id' => $schedule->id,
        ]);

        return $schedule;
    }
}
