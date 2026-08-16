<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceMutationAuthority;
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
        private readonly AllianceIntelligenceMutationAuthority $authority,
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
        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Unsupported scheduled report cadence.');
        }

        return DB::transaction(function () use ($actor, $alliance, $recipient, $name, $cadence, $timezone, $nextDueAt): ContributionReportSchedule {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::ContributionManage);

            $currentRecipient = (string) $recipient->id === (string) $context->actor->id
                ? $context->actor
                : Player::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if ((string) $currentRecipient->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw new InvalidArgumentException('Scheduled report recipient must belong to the Alliance Kingdom.');
            }

            $recipientMembership = (string) $currentRecipient->id === (string) $context->actor->id
                ? $context->membership
                : AllianceMembership::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('player_id', $currentRecipient->id)
                    ->where('status', MembershipStatus::Active->value)
                    ->lockForUpdate()
                    ->first();
            if (! $recipientMembership instanceof AllianceMembership) {
                throw new InvalidArgumentException('Scheduled report recipient must be an active Alliance Player.');
            }

            $schedule = ContributionReportSchedule::query()->create([
                'alliance_id' => $context->alliance->id,
                'recipient_player_id' => $currentRecipient->id,
                'name' => $name,
                'cadence' => $cadence,
                'timezone' => $timezone,
                'next_due_at' => $nextDueAt->utc(),
                'report_version' => ContributionReportExporter::REPORT_VERSION,
                'is_enabled' => true,
                'created_by_player_id' => $context->actor->id,
            ]);

            $this->audit->record('contribution.report-schedule.created', $context->actor, $schedule, $context->alliance, [
                'cadence' => $cadence,
                'recipient_player_id' => $currentRecipient->id,
                'report_version' => ContributionReportExporter::REPORT_VERSION,
            ]);
            $this->outbox->record('contribution.report-schedule.created', $context->alliance->id, $schedule, [
                'schedule_id' => $schedule->id,
                'recipient_player_id' => $currentRecipient->id,
                'report_version' => ContributionReportExporter::REPORT_VERSION,
            ]);

            return $schedule;
        });
    }
}
