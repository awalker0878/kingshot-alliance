<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementKey;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferKingdomConditionSelector;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class TransferSelfEligibilityQuery
{
    public function __construct(
        private TransferAuthorization $authorization,
        private TransferPlanQuery $plans,
        private KingdomReferenceQuery $kingdoms,
        private TransferEligibilityEvaluator $evaluator,
        private TransferObservationSelector $selector,
        private TransferKingdomConditionSelector $conditionSelector,
        private TransferCapacityPlanningQuery $capacity,
    ) {}

    /** @return array<string,mixed>|null */
    public function forPlayer(string $actorPlayerId, string $allianceId, ?int $targetKingdomNumber = null): ?array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            return null;
        }

        $plan = $this->plans->currentForAlliance($allianceId);
        if (! $plan instanceof TransferPlan) {
            return null;
        }

        $participant = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)
            ->where('player_id', $actorPlayerId)
            ->whereNull('withdrawn_at')
            ->with(['sourceKingdom:id,number', 'destinationKingdom:id,number'])
            ->first();
        if (! $participant instanceof TransferParticipant) {
            return null;
        }

        $targetId = $participant->direction->value === 'incoming'
            ? (string) $plan->home_kingdom_id
            : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
        $sourceId = $participant->source_kingdom_id === null ? null : (string) $participant->source_kingdom_id;

        if ($targetKingdomNumber !== null) {
            $requestedTarget = $this->kingdoms->findByNumber($targetKingdomNumber);
            if ($requestedTarget === null || $targetId === null || $requestedTarget->kingdomId !== $targetId) {
                return null;
            }
        }

        $now = CarbonImmutable::now('UTC');
        $observations = TransferObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)
            ->where('transfer_participant_id', $participant->id)
            ->with('targetKingdom:id,number')
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();

        if ($participant->direction->value === 'staying') {
            $assessment = new TransferEligibilityAssessment(
                TransferEligibilityOutcome::NotApplicable,
                [new TransferRequirement(TransferRequirementKey::WindowPhase, TransferRequirementState::NotApplicable, 'This Governor is staying in the current Kingdom.')],
                null,
                $now,
            );

            return $this->result($participant, $plan, $assessment, $observations, null, null, $targetId, null);
        }

        if ($targetId === null || $sourceId === null) {
            $assessment = new TransferEligibilityAssessment(
                TransferEligibilityOutcome::NeedsVerification,
                [new TransferRequirement(TransferRequirementKey::TransferGroup, TransferRequirementState::Unknown, 'Source or target Kingdom is missing.', null, null, 'Set both source and target Kingdoms.')],
                'Set the target Kingdom before evaluating transfer eligibility.',
                $now,
            );

            return $this->result($participant, $plan, $assessment, $observations, null, null, $targetId, null);
        }

        $groups = TransferGroup::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $plan->window->id)
            ->whereNull('superseded_at')
            ->with('kingdoms:id')
            ->get();
        $groupsByKingdom = [];
        foreach ($groups as $group) {
            foreach ($group->kingdoms as $kingdom) {
                $groupsByKingdom[(string) $kingdom->id] = $group;
            }
        }
        $sourceGroup = $groupsByKingdom[$sourceId] ?? null;
        $targetGroup = $groupsByKingdom[$targetId] ?? null;
        $sourceLabel = $sourceGroup instanceof TransferGroup ? $sourceGroup->official_label : null;
        $targetLabel = $targetGroup instanceof TransferGroup ? $targetGroup->official_label : null;
        $groupState = $sourceGroup instanceof TransferGroup
            && $targetGroup instanceof TransferGroup
            && $sourceGroup->source_type->isAuthoritative()
            && $targetGroup->source_type->isAuthoritative()
                ? TransferRequirementState::Met
                : TransferRequirementState::Unknown;

        /** @var Collection<int,TransferKingdomConditionObservation> $conditions */
        $conditions = TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $plan->window->id)
            ->where('kingdom_id', $targetId)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();
        $condition = $conditions->first();
        $projection = $this->capacity->forTargets($allianceId, (string) $plan->window->id, [$targetId])[$targetId] ?? null;

        $input = new TransferEligibilityInput(
            phase: $plan->window->phaseAt($now),
            groupState: $groupState,
            sourceGroupLabel: $sourceLabel,
            targetGroupLabel: $targetLabel,
            targetPowerCap: $this->conditionSelector->value($conditions, 'power_cap'),
            targetClassification: $this->conditionSelector->classification($conditions),
            targetHeroGeneration: $this->conditionSelector->value($conditions, 'hero_generation'),
            targetTruegoldLevel: $this->conditionSelector->value($conditions, 'truegold_level'),
            targetCharacterAgeThresholdDays: $this->conditionSelector->value($conditions, 'character_age_threshold_days'),
            targetCapacityRemaining: $projection?->totalRemaining() ?? TransferObservedValue::unknown(),
            invitationCapacityRemaining: $projection?->ordinaryInviteRemaining() ?? TransferObservedValue::unknown(),
            transferOpenCapacityRemaining: $projection?->transferOpenRemaining() ?? TransferObservedValue::unknown(),
            specialInvitesAvailable: $projection?->specialInviteRemaining() ?? TransferObservedValue::unknown(),
            governorPower: $this->selector->select($observations, TransferObservationKind::GovernorPower, null, $now),
            governorHeroGeneration: $this->selector->select($observations, TransferObservationKind::HeroGeneration, null, $now),
            governorTruegoldLevel: $this->selector->select($observations, TransferObservationKind::TruegoldLevel, null, $now),
            characterAgeOverTargetDays: $this->selector->select($observations, TransferObservationKind::CharacterAgeOverTargetDays, $targetId, $now),
            transferCooldownRemainingDays: $this->selector->select($observations, TransferObservationKind::TransferCooldownRemainingDays, null, $now),
            targetExistingCharacterCount: $this->selector->select($observations, TransferObservationKind::TargetExistingCharacterCount, $targetId, $now),
            invitationStatus: $this->selector->select($observations, TransferObservationKind::InvitationStatus, $targetId, $now),
            passesAvailable: $this->selector->select($observations, TransferObservationKind::TransferPassesAvailable, null, $now),
            passesRequired: $this->selector->select($observations, TransferObservationKind::TransferPassesRequired, $targetId, $now),
            inGameRulesVerified: $this->selector->select($observations, TransferObservationKind::InGameRulesVerified, $targetId, $now),
        );
        $assessment = $this->evaluator->evaluate($input, $now);

        return $this->result(
            $participant,
            $plan,
            $assessment,
            $observations,
            $targetGroup instanceof TransferGroup ? $targetGroup : null,
            $condition instanceof TransferKingdomConditionObservation ? $condition : null,
            $targetId,
            $projection,
        );
    }

    /**
     * @param  Collection<int,TransferObservation>  $observations
     * @return array<string,mixed>
     */
    private function result(
        TransferParticipant $participant,
        TransferPlan $plan,
        TransferEligibilityAssessment $assessment,
        Collection $observations,
        ?TransferGroup $targetGroup,
        ?TransferKingdomConditionObservation $targetCondition,
        ?string $targetId,
        ?TransferKingdomCapacityProjection $capacity,
    ): array
    {
        $requirements = [];
        foreach ($assessment->requirements as $requirement) {
            $requirements[] = [
                'key' => $requirement->key->value,
                'state' => $requirement->state->value,
                'explanation' => $requirement->explanation,
                'actual' => $requirement->actual,
                'required' => $requirement->required,
                'nextAction' => $requirement->nextAction,
                'sourceType' => $requirement->sourceType?->value,
                'sourceReference' => $requirement->sourceReference,
                'observedAt' => $requirement->observedAt?->toIso8601String(),
                'validUntil' => $requirement->validUntil?->toIso8601String(),
            ];
        }

        $targetNumber = null;
        if ($targetId !== null) {
            if ($participant->direction->value === 'incoming') {
                $targetNumber = $plan->homeKingdom?->number;
            } elseif ($participant->destinationKingdom !== null) {
                $targetNumber = $participant->destinationKingdom->number;
            }
        }

        $sourceReferences = [];
        foreach ($requirements as $requirement) {
            if (is_string($requirement['sourceReference'] ?? null) && $requirement['sourceReference'] !== '') {
                $sourceReferences[] = $requirement['sourceReference'];
            }
        }

        return [
            'participantId' => (string) $participant->id,
            'planId' => (string) $plan->id,
            'windowId' => (string) $plan->transfer_window_id,
            'direction' => $participant->direction->value,
            'readinessState' => $participant->readiness_state->value,
            'targetKingdomId' => $targetId,
            'targetKingdomNumber' => $targetNumber,
            'outcome' => $assessment->outcome->value,
            'requirements' => $requirements,
            'primaryAction' => $assessment->primaryAction,
            'evaluatedAt' => $assessment->evaluatedAt->toIso8601String(),
            'targetGroupLabel' => $targetGroup?->official_label,
            'targetConditionId' => $targetCondition?->id,
            'capacity' => $capacity === null ? null : [
                'observedTotalRemaining' => $capacity->totalRemaining()->value,
                'projectedTotalRemaining' => $capacity->totalRemaining(true)->value,
                'observedOrdinaryInviteRemaining' => $capacity->ordinaryInviteRemaining()->value,
                'projectedOrdinaryInviteRemaining' => $capacity->ordinaryInviteRemaining(true)->value,
                'observedTransferOpenRemaining' => $capacity->transferOpenRemaining()->value,
                'projectedTransferOpenRemaining' => $capacity->transferOpenRemaining(true)->value,
                'observedSpecialInvitesAvailable' => $capacity->specialInviteRemaining()->value,
                'projectedSpecialInvitesAvailable' => $capacity->specialInviteRemaining(true)->value,
            ],
            'sourceReferences' => array_values(array_unique($sourceReferences)),
            'observationCount' => $observations->count(),
        ];
    }
}