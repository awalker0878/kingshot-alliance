<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEvidencePreviewKind;
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
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferKingdomConditionSelector;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidencePreviewInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class TransferEvidencePreviewQuery
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private TransferEligibilityQuery $eligibility,
        private TransferEligibilityEvaluator $evaluator,
        private TransferObservationSelector $selector,
        private TransferKingdomConditionSelector $conditionSelector,
        private TransferCapacityPlanningQuery $capacity,
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
                'reviewed_fact_keys' => $this->factKeys($review),
                'transfer_score_before' => $currentScore->value,
                'transfer_score_after' => $review->kind === TransferEvidencePreviewKind::ScorePasses ? $review->transferScore : $currentScore->value,
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

        $conditionRows = $this->conditionRows($allianceId, $scope->transferWindowId, $targetId);
        [$powerCap, $classification] = $this->conditionFacts($conditionRows, $review);
        $projection = $targetId === null
            ? null
            : ($this->capacity->forTargets($allianceId, $scope->transferWindowId, [$targetId])[$targetId] ?? null);

        $governorPower = $review->kind === TransferEvidencePreviewKind::GovernorStatus
            ? $this->reviewedFact($review->governorPower, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::GovernorPower, null, $now);
        $invitation = $review->kind === TransferEvidencePreviewKind::Invitation
            ? $this->reviewedFact($review->invitationStatus, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::InvitationStatus, $targetId, $now);
        $passesAvailable = $review->kind === TransferEvidencePreviewKind::ScorePasses
            ? $this->reviewedFact($review->passesAvailable, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::TransferPassesAvailable, null, $now);
        $passesRequired = $review->kind === TransferEvidencePreviewKind::ScorePasses
            ? $this->reviewedFact($review->passesRequired, $review, $now)
            : $this->selector->select($rows, TransferObservationKind::TransferPassesRequired, $targetId, $now);

        $after = $this->evaluator->evaluate(new TransferEligibilityInput(
            phase: $plan->window->phaseAt($now),
            groupState: $groupState,
            sourceGroupLabel: $sourceGroupLabel,
            targetGroupLabel: $targetGroupLabel,
            targetPowerCap: $powerCap,
            targetClassification: $classification,
            targetHeroGeneration: $this->conditionSelector->value($conditionRows, 'hero_generation'),
            targetTruegoldLevel: $this->conditionSelector->value($conditionRows, 'truegold_level'),
            targetCharacterAgeThresholdDays: $this->conditionSelector->value($conditionRows, 'character_age_threshold_days'),
            targetCapacityRemaining: $projection?->totalRemaining() ?? TransferObservedValue::unknown(),
            invitationCapacityRemaining: $projection?->ordinaryInviteRemaining() ?? TransferObservedValue::unknown(),
            transferOpenCapacityRemaining: $projection?->transferOpenRemaining() ?? TransferObservedValue::unknown(),
            specialInvitesAvailable: $projection?->specialInviteRemaining() ?? TransferObservedValue::unknown(),
            governorPower: $governorPower,
            governorHeroGeneration: $this->selector->select($rows, TransferObservationKind::HeroGeneration, null, $now),
            governorTruegoldLevel: $this->selector->select($rows, TransferObservationKind::TruegoldLevel, null, $now),
            characterAgeOverTargetDays: $this->selector->select($rows, TransferObservationKind::CharacterAgeOverTargetDays, $targetId, $now),
            transferCooldownRemainingDays: $this->selector->select($rows, TransferObservationKind::TransferCooldownRemainingDays, null, $now),
            targetExistingCharacterCount: $this->selector->select($rows, TransferObservationKind::TargetExistingCharacterCount, $targetId, $now),
            invitationStatus: $invitation,
            passesAvailable: $passesAvailable,
            passesRequired: $passesRequired,
            inGameRulesVerified: $this->selector->select($rows, TransferObservationKind::InGameRulesVerified, $targetId, $now),
        ), $now);

        return [
            'current_outcome' => $currentAssessment->outcome->value,
            'current_primary_action' => $currentAssessment->primaryAction,
            'after_outcome' => $after->outcome->value,
            'after_primary_action' => $after->primaryAction,
            'reviewed_fact_keys' => $this->factKeys($review),
            'transfer_score_before' => $currentScore->value,
            'transfer_score_after' => $review->kind === TransferEvidencePreviewKind::ScorePasses ? $review->transferScore : $currentScore->value,
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
        if ($review->kind === TransferEvidencePreviewKind::OfficialGroup) {
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

    /** @param Collection<int,TransferKingdomConditionObservation> $rows
     *  @return array{0:TransferObservedValue,1:TransferKingdomClassification}
     */
    private function conditionFacts(Collection $rows, TransferEvidencePreviewInput $review): array
    {
        $currentPowerCap = $this->conditionSelector->value($rows, 'power_cap');
        $currentClassification = $this->conditionSelector->classification($rows);
        if ($review->kind !== TransferEvidencePreviewKind::TargetKingdomRules) {
            return [$currentPowerCap, $currentClassification];
        }

        $classification = $review->kingdomClassification === null
            ? $currentClassification
            : TransferKingdomClassification::from($review->kingdomClassification);

        return [
            new TransferObservedValue(
                $review->targetPowerCap === null ? TransferRequirementState::Unknown : TransferRequirementState::Met,
                $review->targetPowerCap,
                TransferSourceType::Evidence,
                'Reviewed screenshot preview',
                CarbonImmutable::parse($review->observedAt)->utc(),
            ),
            $classification,
        ];
    }

    /** @return Collection<int,TransferKingdomConditionObservation> */
    private function conditionRows(string $allianceId, string $windowId, ?string $targetId): Collection
    {
        if ($targetId === null) {
            return collect();
        }

        return TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->where('kingdom_id', $targetId)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return list<string> */
    private function factKeys(TransferEvidencePreviewInput $review): array
    {
        return match ($review->kind) {
            TransferEvidencePreviewKind::GovernorStatus => ['governor_power'],
            TransferEvidencePreviewKind::ScorePasses => ['transfer_score', 'transfer_passes_available', 'transfer_passes_required'],
            TransferEvidencePreviewKind::Invitation => ['invitation_status'],
            TransferEvidencePreviewKind::TargetKingdomRules => $review->kingdomClassification === null
                ? ['target_power_cap']
                : ['target_power_cap', 'kingdom_classification'],
            TransferEvidencePreviewKind::OfficialGroup => ['official_transfer_group'],
        };
    }
}
