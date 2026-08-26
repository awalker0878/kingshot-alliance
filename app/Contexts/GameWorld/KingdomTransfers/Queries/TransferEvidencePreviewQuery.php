<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidencePreviewInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class TransferEvidencePreviewQuery
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private TransferEligibilityQuery $eligibility,
        private TransferEligibilityEvaluator $evaluator,
        private TransferObservationSelector $selector,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    /**
     * @return array{
     *   current_outcome:string,
     *   current_primary_action:?string,
     *   after_outcome:string,
     *   after_primary_action:?string,
     *   reviewed_fact_keys:list<string>,
     *   transfer_score_before:int|string|bool|null,
     *   transfer_score_after:int|string|bool|null
     * }
     */
    public function preview(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        TransferEvidencePreviewInput $review,
    ): array {
        $scope = $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
        $plan = TransferPlan::query()
            ->where('alliance_id', $allianceId)
            ->whereKey($planId)
            ->with('window')
            ->firstOrFail();
        $participant = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->whereKey($participantId)
            ->firstOrFail();
        $current = $this->eligibility->forPlan($allianceId, $plan, collect([$participant]))[$participantId];
        $currentAssessment = $current['assessment'];
        $currentScore = $current['transferScore'];

        if ($participant->direction->value === 'staying') {
            return [
                'current_outcome' => $currentAssessment->outcome->value,
                'current_primary_action' => $currentAssessment->primaryAction,
                'after_outcome' => $currentAssessment->outcome->value,
                'after_primary_action' => $currentAssessment->primaryAction,
                'reviewed_fact_keys' => $this->factKeys($review->kind),
                'transfer_score_before' => $currentScore->value,
                'transfer_score_after' => $review->kind === EvidenceKind::TransferScorePasses ? $review->transferScore : $currentScore->value,
            ];
        }

        /** @var Collection<int, TransferObservation> $rows */
        $rows = $current['observations'];
        $now = CarbonImmutable::now('UTC');
        $sourceId = $participant->source_kingdom_id === null ? null : (string) $participant->source_kingdom_id;
        $targetId = $scope->targetKingdomId;
        [$groupState, $sourceGroupLabel, $targetGroupLabel] = $this->groupFacts(
            $allianceId,
            $scope->transferWindowId,
            $sourceId,
            $targetId,
            $review,
        );
        [$powerCap, $classification] = $this->conditionFacts(
            $allianceId,
            $scope->transferWindowId,
            $targetId,
            $review,
        );

        $governorPower = $review->kind === EvidenceKind::TransferGovernorStatus
            ? $this->reviewedFact($review->governorPower, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::GovernorPower, null, $now);
        $invitation = $review->kind === EvidenceKind::TransferInvitation
            ? $this->reviewedFact($review->invitationStatus, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::InvitationStatus, $targetId, $now);
        $passesAvailable = $review->kind === EvidenceKind::TransferScorePasses
            ? $this->reviewedFact($review->passesAvailable, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::TransferPassesAvailable, null, $now);
        $passesRequired = $review->kind === EvidenceKind::TransferScorePasses
            ? $this->reviewedFact($review->passesRequired, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::TransferPassesRequired, $targetId, $now);
        $inGameRulesVerified = $this->selector->select($rows, TransferObservationKind::InGameRulesVerified, $targetId, $now);

        $after = $this->evaluator->evaluate(new TransferEligibilityInput(
            phase: $plan->window->phaseAt($now),
            groupState: $groupState,
            sourceGroupLabel: $sourceGroupLabel,
            targetGroupLabel: $targetGroupLabel,
            targetPowerCap: $powerCap,
            targetClassification: $classification,
            governorPower: $governorPower,
            invitationStatus: $invitation,
            passesAvailable: $passesAvailable,
            passesRequired: $passesRequired,
            inGameRulesVerified: $inGameRulesVerified,
        ), $now);

        return [
            'current_outcome' => $currentAssessment->outcome->value,
            'current_primary_action' => $currentAssessment->primaryAction,
            'after_outcome' => $after->outcome->value,
            'after_primary_action' => $after->primaryAction,
            'reviewed_fact_keys' => $this->factKeys($review->kind),
            'transfer_score_before' => $currentScore->value,
            'transfer_score_after' => $review->kind === EvidenceKind::TransferScorePasses ? $review->transferScore : $currentScore->value,
        ];
    }

    private function reviewedFact(int|string|bool|null $value, TransferEvidencePreviewInput $review, CarbonImmutable $now): TransferObservedValue
    {
        $observedAt = CarbonImmutable::parse($review->observedAt)->utc();
        $validUntil = $review->validUntil === null ? null : CarbonImmutable::parse($review->validUntil)->utc();
        $state = $validUntil !== null && $validUntil->lt($now)
            ? TransferRequirementState::Stale
            : ($validUntil === null ? TransferRequirementState::Unknown : TransferRequirementState::Met);

        return new TransferObservedValue(
            state: $state,
            value: $value,
            sourceType: TransferSourceType::Evidence,
            sourceReference: 'Reviewed screenshot preview',
            observedAt: $observedAt,
            validUntil: $validUntil,
        );
    }

    /** @return array{0:TransferRequirementState,1:?string,2:?string} */
    private function groupFacts(string $allianceId, string $windowId, ?string $sourceId, ?string $targetId, TransferEvidencePreviewInput $review): array
    {
        if ($review->kind === EvidenceKind::TransferOfficialGroup) {
            if ($sourceId === null || $targetId === null || $review->officialGroupIdentifier === null) {
                return [TransferRequirementState::Unknown, null, null];
            }
            $source = $this->kingdoms->require($sourceId);
            $target = $this->kingdoms->require($targetId);
            $members = array_fill_keys($review->officialGroupKingdomNumbers, true);
            if (isset($members[$source->number], $members[$target->number])) {
                return [TransferRequirementState::Met, $review->officialGroupIdentifier, $review->officialGroupIdentifier];
            }

            return [TransferRequirementState::Unknown, null, null];
        }

        $groups = TransferGroup::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereNull('superseded_at')
            ->with('kingdoms:id')
            ->get();
        $byKingdom = [];
        foreach ($groups as $group) {
            foreach ($group->kingdoms as $kingdom) {
                $byKingdom[(string) $kingdom->id] = $group;
            }
        }
        $source = $sourceId === null ? null : ($byKingdom[$sourceId] ?? null);
        $target = $targetId === null ? null : ($byKingdom[$targetId] ?? null);
        $state = $source instanceof TransferGroup
            && $target instanceof TransferGroup
            && $source->source_type->isAuthoritative()
            && $target->source_type->isAuthoritative()
                ? TransferRequirementState::Met
                : TransferRequirementState::Unknown;

        return [
            $state,
            $source instanceof TransferGroup ? (string) $source->official_label : null,
            $target instanceof TransferGroup ? (string) $target->official_label : null,
        ];
    }

    /** @return array{0:TransferObservedValue,1:TransferKingdomClassification} */
    private function conditionFacts(string $allianceId, string $windowId, ?string $targetId, TransferEvidencePreviewInput $review): array
    {
        if ($review->kind === EvidenceKind::TransferTargetKingdomRules) {
            $classification = TransferKingdomClassification::tryFrom((string) $review->kingdomClassification)
                ?? TransferKingdomClassification::Unknown;

            return [
                new TransferObservedValue(
                    TransferRequirementState::Met,
                    $review->targetPowerCap,
                    TransferSourceType::Evidence,
                    'Reviewed screenshot preview',
                    CarbonImmutable::parse($review->observedAt)->utc(),
                ),
                $classification,
            ];
        }
        if ($targetId === null) {
            return [TransferObservedValue::unknown(), TransferKingdomClassification::Unknown];
        }

        $rows = TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->where('kingdom_id', $targetId)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();
        $authoritative = $rows->filter(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative())->values();
        if ($authoritative->isEmpty()) {
            return [TransferObservedValue::unknown(), TransferKingdomClassification::Unknown];
        }
        /** @var TransferKingdomConditionObservation $latest */
        $latest = $authoritative->first();
        $sameTime = $authoritative->filter(static fn (TransferKingdomConditionObservation $row): bool => $row->observed_at->equalTo($latest->observed_at));
        if ($sameTime->pluck('power_cap')->unique()->count() > 1) {
            return [
                new TransferObservedValue(TransferRequirementState::Conflicting, null, $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at)),
                TransferKingdomClassification::Unknown,
            ];
        }

        return [
            $latest->power_cap === null
                ? new TransferObservedValue(TransferRequirementState::Unknown, null, $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at))
                : new TransferObservedValue(TransferRequirementState::Met, $latest->power_cap, $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at)),
            $latest->source_type->isAuthoritative() ? $latest->classification : TransferKingdomClassification::Unknown,
        ];
    }

    /** @return list<string> */
    private function factKeys(EvidenceKind $kind): array
    {
        return match ($kind) {
            EvidenceKind::TransferGovernorStatus => ['governor_power'],
            EvidenceKind::TransferScorePasses => ['transfer_score', 'transfer_passes_available', 'transfer_passes_required'],
            EvidenceKind::TransferInvitation => ['invitation_status'],
            EvidenceKind::TransferTargetKingdomRules => ['target_power_cap', 'kingdom_classification'],
            EvidenceKind::TransferOfficialGroup => ['official_transfer_group'],
            default => [],
        };
    }
}
