<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Services\ContributionPeriodResolver;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordContribution
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AllianceReferenceQuery $alliances,
        private readonly PlayerReferenceQuery $players,
        private readonly PlayerMembershipQuery $memberships,
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $targetPlayerId,
        string $categoryId,
        float $value,
        ContributionRecordSource $source,
        ?string $evidence = null,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $targetPlayerId, $categoryId, $value, $source, $evidence): void {
            $permission = $source === ContributionRecordSource::SelfReported
                ? IntelligencePermission::View
                : IntelligencePermission::ContributionManage;
            [$facts, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, $permission);

            $isActorTarget = $targetPlayerId === $actor->playerId;
            if ($source === ContributionRecordSource::SelfReported && ! $isActorTarget) {
                throw new InvalidArgumentException('Self-reported contributions may only be recorded for the active Player.');
            }

            $target = $isActorTarget ? $actor : $this->players->lockCurrent($targetPlayerId);
            if ($target->kingdomId !== $facts->kingdomId) {
                throw new InvalidArgumentException('Contribution Player must belong to the active Alliance Kingdom.');
            }

            if ($source !== ContributionRecordSource::SelfReported
                && ! $isActorTarget
                && ! $this->memberships->lockActiveMember($allianceId, $target->playerId)) {
                throw new InvalidArgumentException('Manual contributions may only target a current active Alliance Player.');
            }

            $category = ContributionCategory::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($categoryId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! $category->is_active) {
                throw new InvalidArgumentException('Contribution category is inactive.');
            }
            if ($source === ContributionRecordSource::SelfReported && ! $category->allow_self_report) {
                throw new InvalidArgumentException('This contribution category does not allow member self-reporting.');
            }
            if ($category->evidence_required && trim((string) $evidence) === '') {
                throw new InvalidArgumentException('Evidence is required for this contribution category.');
            }

            // Alliance row is already locked by AllianceAuthorityFactsQuery; this owner projection
            // supplies non-authority presentation/configuration data needed for period calculation.
            $alliance = $this->alliances->require($allianceId);
            $period = $this->periods->current($category, $alliance->timezone);
            $record = ContributionRecord::query()->create([
                'alliance_id' => $allianceId,
                'category_id' => $category->id,
                'player_id' => $target->playerId,
                'source' => $source,
                'data_class' => $category->data_class,
                'value' => $value,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'status' => ContributionRecordStatus::Pending,
                'evidence' => $evidence,
                'calculation_key' => $category->calculation_key,
                'calculation_version' => $category->calculation_version,
                'recorded_at' => now(),
                'recorded_by_player_id' => $actor->playerId,
            ]);

            $this->audit->record('contribution.record.created', $actor, $record, $allianceId, [
                'source' => $source->value,
                'player_id' => $target->playerId,
                'category_id' => $category->id,
            ]);
            $this->outbox->record('contribution.record.created', $allianceId, $record, [
                'record_id' => $record->id,
                'player_id' => $target->playerId,
                'status' => $record->status->value,
            ]);
        });
    }
}
