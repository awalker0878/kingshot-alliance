<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementKey;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
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
    ) {}

    /**
     * @return array<string,mixed>|null Null means the actor has no legitimate visible self-transfer scope.
     */
    public function forPlayer(
        string $actorPlayerId,
        string $allianceId,
        ?int $targetKingdomNumber = null,
    ): ?array {
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
            : ($participant->destination_kingdom_id === null
                ? null
                : (string) $participant->destination_kingdom_id);
        $sourceId = $participant->source_kingdom_id === null
            ? null
            : (string) $participant->source_kingdom_id;

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
                [new TransferRequirement(
                    TransferRequirementKey::WindowPhase,
                    TransferRequirementState::NotApplicable,
                    'This Governor is staying in the current Kingdom.',
                )],
                null,
                $now,
            );

            return $this->result($participant, $plan, $assessment, $observations, null, null, $targetId);
        }

        if ($targetId === null || $sourceId === null) {
            $assessment = new TransferEligibilityAssessment(
                TransferEligibilityOutcome::NeedsVerification,
                [new TransferRequirement(
                    TransferRequirementKey::TransferGroup,
                    TransferRequirementState::Unknown,
                    'Source or target Kingdom is missing.',
                    null,
                    null,
                    'Set both source and target Kingdoms.',
                )],
                'Set the target Kingdom before evaluating transfer eligibility.',
                $now,
            );

            return $this->result($participant, $plan, $assessment, $observations, null, null, $targetId);
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

        $conditions = TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $plan->window->id)
            ->where('kingdom_id', $targetId)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();
        $condition = $conditions->first();
        $conditionFact = $this->conditionFact($conditions);
        $classification = $condition instanceof TransferKingdomConditionObservation
            && $condition->source_type->isAuthoritative()
                ? ($condition->classification ?? TransferKingdomClassification::Unknown)
                : TransferKingdomClassification::Unknown;

        $input = new TransferEligibilityInput(
            $plan->window->phaseAt($now),
            $groupState,
            $sourceLabel,
            $targetLabel,
            $conditionFact,
            $classification,
            $this->selector->select($observations, TransferObservationKind::GovernorPower, null, $now),
            $this->selector->select($observations, TransferObservationKind::InvitationStatus, $targetId, $now),
            $this->selector->select($observations, TransferObservationKind::TransferPassesAvailable, null, $now),
            $this->selector->select($observations, TransferObservationKind::TransferPassesRequired, $targetId, $now),
            $this->selector->select($observations, TransferObservationKind::InGameRulesVerified, $targetId, $now),
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
    ): array {
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
            'sourceReferences' => array_values(array_unique($sourceReferences)),
            'observationCount' => $observations->count(),
        ];
    }

    /** @param Collection<int,TransferKingdomConditionObservation> $rows */
    private function conditionFact(Collection $rows): TransferObservedValue
    {
        $authoritative = $rows
            ->filter(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative())
            ->values();

        if ($authoritative->isEmpty()) {
            $latest = $rows->first();

            return $latest instanceof TransferKingdomConditionObservation
                ? new TransferObservedValue(
                    TransferRequirementState::Unknown,
                    $latest->power_cap,
                    $latest->source_type,
                    $latest->source_reference,
                    CarbonImmutable::instance($latest->observed_at),
                )
                : TransferObservedValue::unknown();
        }

        /** @var TransferKingdomConditionObservation $latest */
        $latest = $authoritative->first();
        $sameTime = $authoritative->filter(
            static fn (TransferKingdomConditionObservation $row): bool => $row->observed_at->equalTo($latest->observed_at),
        );

        if ($sameTime->pluck('power_cap')->unique()->count() > 1) {
            return new TransferObservedValue(
                TransferRequirementState::Conflicting,
                null,
                $latest->source_type,
                $latest->source_reference,
                CarbonImmutable::instance($latest->observed_at),
            );
        }

        if ($latest->power_cap === null) {
            return new TransferObservedValue(
                TransferRequirementState::Unknown,
                null,
                $latest->source_type,
                $latest->source_reference,
                CarbonImmutable::instance($latest->observed_at),
            );
        }

        return new TransferObservedValue(
            TransferRequirementState::Met,
            $latest->power_cap,
            $latest->source_type,
            $latest->source_reference,
            CarbonImmutable::instance($latest->observed_at),
        );
    }
}
