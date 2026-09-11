<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;

final readonly class TransferSelfEligibilityQuery
{
    public function __construct(
        private TransferAuthorization $authorization,
        private TransferPlanQuery $plans,
        private KingdomReferenceQuery $kingdoms,
        private TransferParticipantQuery $participants,
        private TransferEligibilityQuery $eligibility,
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
        $home = $this->kingdoms->findActive((string) $plan->home_kingdom_id);
        if ($home === null) {
            return null;
        }

        $participant = $this->participants->activeForPlayer($actorPlayerId, $allianceId, (string) $plan->id, $actorPlayerId);
        if ($participant === null) {
            return null;
        }

        $targetId = $participant->direction->value === 'incoming'
            ? $home->kingdomId
            : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
        $target = $targetId === null ? null : ($targetId === $home->kingdomId ? $home : $this->kingdoms->findActive($targetId));
        if ($targetId !== null && $target === null) {
            return null;
        }
        if ($targetKingdomNumber !== null && $target?->number !== $targetKingdomNumber) {
            return null;
        }

        $evaluation = $this->eligibility->forPlan($allianceId, $plan, collect([$participant]))[(string) $participant->id];
        // The response reports complete history volume, not the bounded witness set
        // used by the canonical eligibility calculation.
        $observationCount = TransferObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)
            ->where('transfer_participant_id', $participant->id)
            ->count();

        return $this->result(
            $participant,
            $plan,
            $evaluation['assessment'],
            $observationCount,
            $evaluation['officialGroup'],
            $evaluation['targetCondition'],
            $targetId,
            $target?->number,
            $evaluation['capacityProjection'],
        );
    }

    /** @return array<string,mixed> */
    private function result(
        TransferParticipant $participant,
        TransferPlan $plan,
        TransferEligibilityAssessment $assessment,
        int $observationCount,
        ?TransferGroup $targetGroup,
        ?TransferKingdomConditionObservation $targetCondition,
        ?string $targetId,
        ?int $targetNumber,
        ?TransferKingdomCapacityProjection $capacity,
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
            'observationCount' => $observationCount,
        ];
    }
}
