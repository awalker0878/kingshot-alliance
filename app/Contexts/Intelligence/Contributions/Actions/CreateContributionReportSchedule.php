<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Models\ContributionReportSchedule;
use App\Contexts\Intelligence\Contributions\Services\ContributionReportExporter;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateContributionReportSchedule
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly PlayerReferenceQuery $players,
        private readonly PlayerMembershipQuery $memberships,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $recipientPlayerId,
        string $name,
        string $cadence,
        string $timezone,
        CarbonImmutable $nextDueAt,
    ): void {
        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Unsupported scheduled report cadence.');
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $recipientPlayerId, $name, $cadence, $timezone, $nextDueAt): void {
            [$facts, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $recipient = $recipientPlayerId === $actor->playerId
                ? $actor
                : $this->players->lockCurrent($recipientPlayerId);

            if ($recipient->kingdomId !== $facts->kingdomId) {
                throw new InvalidArgumentException('Scheduled report recipient must belong to the Alliance Kingdom.');
            }
            if ($recipient->playerId !== $actor->playerId && ! $this->memberships->lockActiveMember($allianceId, $recipient->playerId)) {
                throw new InvalidArgumentException('Scheduled report recipient must be an active Alliance Player.');
            }

            $schedule = ContributionReportSchedule::query()->create([
                'alliance_id' => $allianceId,
                'recipient_player_id' => $recipient->playerId,
                'name' => $name,
                'cadence' => $cadence,
                'timezone' => $timezone,
                'next_due_at' => $nextDueAt->utc(),
                'report_version' => ContributionReportExporter::REPORT_VERSION,
                'is_enabled' => true,
                'created_by_player_id' => $actor->playerId,
            ]);

            $this->audit->record('contribution.report-schedule.created', $actor, $schedule, $allianceId, [
                'cadence' => $cadence,
                'recipient_player_id' => $recipient->playerId,
                'report_version' => ContributionReportExporter::REPORT_VERSION,
            ]);
            $this->outbox->record('contribution.report-schedule.created', $allianceId, $schedule, [
                'schedule_id' => $schedule->id,
                'recipient_player_id' => $recipient->playerId,
                'report_version' => ContributionReportExporter::REPORT_VERSION,
            ]);
        });
    }
}
