<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

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

final readonly class TransferEligibilityQuery
{
    public function __construct(
        private TransferEligibilityEvaluator $evaluator,
        private TransferObservationSelector $selector,
        private TransferKingdomConditionSelector $conditionSelector,
        private TransferCapacityPlanningQuery $capacity,
    ) {}

    /**
     * @param  Collection<int, TransferParticipant>  $participants
     * @return array<string, array{
     *     assessment: TransferEligibilityAssessment,
     *     transferScore: TransferObservedValue,
     *     observations: Collection<int, TransferObservation>,
     *     officialGroup: TransferGroup|null,
     *     targetCondition: TransferKingdomConditionObservation|null,
     *     capacityProjection: TransferKingdomCapacityProjection|null
     * }>
     */
    public function forPlan(string $allianceId, TransferPlan $plan, Collection $participants): array
    {
        $now = CarbonImmutable::now('UTC');
        $window = $plan->window;
        $groups = TransferGroup::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $window->id)
            ->whereNull('superseded_at')
            ->with('kingdoms:id')
            ->get();
        $conditions = TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $window->id)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();
        $observations = TransferObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)
            ->with('targetKingdom:id,number')
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('transfer_participant_id');

        $groupsByKingdom = [];
        foreach ($groups as $group) {
            foreach ($group->kingdoms as $kingdom) {
                $groupsByKingdom[(string) $kingdom->id] = $group;
            }
        }

        $targetIds = [];
        foreach ($participants as $participant) {
            $targetId = $this->targetId($plan, $participant);
            if ($targetId !== null) {
                $targetIds[] = $targetId;
            }
        }
        $capacityByKingdom = $this->capacity->forTargets($allianceId, (string) $window->id, $targetIds);

        $result = [];
        foreach ($participants as $participant) {
            /** @var Collection<int, TransferObservation> $rows */
            $rows = $observations->get((string) $participant->id, collect());
            $targetId = $this->targetId($plan, $participant);
            $sourceId = $participant->source_kingdom_id === null ? null : (string) $participant->source_kingdom_id;

            if ($participant->direction->value === 'staying') {
                $assessment = new TransferEligibilityAssessment(
                    TransferEligibilityOutcome::NotApplicable,
                    [new TransferRequirement(TransferRequirementKey::WindowPhase, TransferRequirementState::NotApplicable, 'This Governor is staying in the current Kingdom.')],
                    null,
                    $now,
                );
                $result[(string) $participant->id] = [
                    'assessment' => $assessment,
                    'transferScore' => $this->selector->select($rows, TransferObservationKind::TransferScore, null, $now),
                    'observations' => $rows,
                    'officialGroup' => null,
                    'targetCondition' => null,
                    'capacityProjection' => null,
                ];

                continue;
            }

            if ($targetId === null || $sourceId === null) {
                $assessment = new TransferEligibilityAssessment(
                    TransferEligibilityOutcome::NeedsVerification,
                    [new TransferRequirement(TransferRequirementKey::TransferGroup, TransferRequirementState::Unknown, 'Source or target Kingdom is missing.', null, null, 'Set both source and target Kingdoms.')],
                    'Set the target Kingdom before evaluating transfer eligibility.',
                    $now,
                );
                $result[(string) $participant->id] = [
                    'assessment' => $assessment,
                    'transferScore' => $this->selector->select($rows, TransferObservationKind::TransferScore, null, $now),
                    'observations' => $rows,
                    'officialGroup' => null,
                    'targetCondition' => null,
                    'capacityProjection' => null,
                ];

                continue;
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

            /** @var Collection<int,TransferKingdomConditionObservation> $targetConditions */
            $targetConditions = $conditions->where('kingdom_id', $targetId)->values();
            $condition = $targetConditions->first();
            $projection = $capacityByKingdom[$targetId] ?? null;

            $input = new TransferEligibilityInput(
                phase: $window->phaseAt($now),
                groupState: $groupState,
                sourceGroupLabel: $sourceLabel,
                targetGroupLabel: $targetLabel,
                targetPowerCap: $this->conditionSelector->value($targetConditions, 'power_cap'),
                targetClassification: $this->conditionSelector->classification($targetConditions),
                targetHeroGeneration: $this->conditionSelector->value($targetConditions, 'hero_generation'),
                targetTruegoldLevel: $this->conditionSelector->value($targetConditions, 'truegold_level'),
                targetCharacterAgeThresholdDays: $this->conditionSelector->value($targetConditions, 'character_age_threshold_days'),
                targetCapacityRemaining: $projection?->totalRemaining() ?? TransferObservedValue::unknown(),
                invitationCapacityRemaining: $projection?->ordinaryInviteRemaining() ?? TransferObservedValue::unknown(),
                transferOpenCapacityRemaining: $projection?->transferOpenRemaining() ?? TransferObservedValue::unknown(),
                specialInvitesAvailable: $projection?->specialInviteRemaining() ?? TransferObservedValue::unknown(),
                governorPower: $this->selector->select($rows, TransferObservationKind::GovernorPower, null, $now),
                governorHeroGeneration: $this->selector->select($rows, TransferObservationKind::HeroGeneration, null, $now),
                governorTruegoldLevel: $this->selector->select($rows, TransferObservationKind::TruegoldLevel, null, $now),
                characterAgeOverTargetDays: $this->selector->select($rows, TransferObservationKind::CharacterAgeOverTargetDays, $targetId, $now),
                transferCooldownRemainingDays: $this->selector->select($rows, TransferObservationKind::TransferCooldownRemainingDays, null, $now),
                targetExistingCharacterCount: $this->selector->select($rows, TransferObservationKind::TargetExistingCharacterCount, $targetId, $now),
                invitationStatus: $this->selector->select($rows, TransferObservationKind::InvitationStatus, $targetId, $now),
                passesAvailable: $this->selector->select($rows, TransferObservationKind::TransferPassesAvailable, null, $now),
                passesRequired: $this->selector->select($rows, TransferObservationKind::TransferPassesRequired, $targetId, $now),
                resourceProtectionVerified: $this->selector->select($rows, TransferObservationKind::ResourceProtectionVerified, null, $now),
                inGameRulesVerified: $this->selector->select($rows, TransferObservationKind::InGameRulesVerified, $targetId, $now),
            );

            $result[(string) $participant->id] = [
                'assessment' => $this->evaluator->evaluate($input, $now),
                'transferScore' => $this->selector->select($rows, TransferObservationKind::TransferScore, null, $now),
                'observations' => $rows,
                'officialGroup' => $targetGroup instanceof TransferGroup ? $targetGroup : null,
                'targetCondition' => $condition instanceof TransferKingdomConditionObservation ? $condition : null,
                'capacityProjection' => $projection,
            ];
        }

        return $result;
    }

    private function targetId(TransferPlan $plan, TransferParticipant $participant): ?string
    {
        return $participant->direction->value === 'incoming'
            ? (string) $plan->home_kingdom_id
            : ($participant->destination_kingdom_id === null ? null : (string) $participant->destination_kingdom_id);
    }
}
