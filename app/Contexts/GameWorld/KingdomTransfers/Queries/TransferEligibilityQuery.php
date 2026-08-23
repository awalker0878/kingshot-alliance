<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

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

final readonly class TransferEligibilityQuery
{
    public function __construct(
        private TransferEligibilityEvaluator $evaluator,
        private TransferObservationSelector $selector,
    ) {}

    /**
     * @param  Collection<int, TransferParticipant>  $participants
     * @return array<string, array{
     *     assessment: TransferEligibilityAssessment,
     *     transferScore: TransferObservedValue,
     *     observations: Collection<int, TransferObservation>,
     *     officialGroup: TransferGroup|null,
     *     targetCondition: TransferKingdomConditionObservation|null
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

        $result = [];
        foreach ($participants as $participant) {
            /** @var Collection<int, TransferObservation> $rows */
            $rows = $observations->get((string) $participant->id, collect());
            $targetId = $participant->direction->value === 'incoming'
                ? (string) $plan->home_kingdom_id
                : ($participant->destination_kingdom_id === null
                    ? null
                    : (string) $participant->destination_kingdom_id);
            $sourceId = $participant->source_kingdom_id === null
                ? null
                : (string) $participant->source_kingdom_id;

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
                $result[(string) $participant->id] = [
                    'assessment' => $assessment,
                    'transferScore' => $this->selector->select(
                        $rows,
                        TransferObservationKind::TransferScore,
                        null,
                        $now,
                    ),
                    'observations' => $rows,
                    'officialGroup' => null,
                    'targetCondition' => null,
                ];

                continue;
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
                $result[(string) $participant->id] = [
                    'assessment' => $assessment,
                    'transferScore' => $this->selector->select(
                        $rows,
                        TransferObservationKind::TransferScore,
                        null,
                        $now,
                    ),
                    'observations' => $rows,
                    'officialGroup' => null,
                    'targetCondition' => null,
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

            $targetConditions = $conditions->where('kingdom_id', $targetId);
            $condition = $targetConditions->first();
            $conditionFact = $this->conditionFact($targetConditions);
            $classification = $condition instanceof TransferKingdomConditionObservation
                && $condition->source_type->isAuthoritative()
                    ? $condition->classification
                    : TransferKingdomClassification::Unknown;

            $input = new TransferEligibilityInput(
                $window->phaseAt($now),
                $groupState,
                $sourceLabel,
                $targetLabel,
                $conditionFact,
                $classification,
                $this->selector->select($rows, TransferObservationKind::GovernorPower, null, $now),
                $this->selector->select($rows, TransferObservationKind::InvitationStatus, $targetId, $now),
                $this->selector->select($rows, TransferObservationKind::TransferPassesAvailable, null, $now),
                $this->selector->select($rows, TransferObservationKind::TransferPassesRequired, $targetId, $now),
                $this->selector->select($rows, TransferObservationKind::InGameRulesVerified, $targetId, $now),
            );

            $result[(string) $participant->id] = [
                'assessment' => $this->evaluator->evaluate($input, $now),
                'transferScore' => $this->selector->select(
                    $rows,
                    TransferObservationKind::TransferScore,
                    null,
                    $now,
                ),
                'observations' => $rows,
                'officialGroup' => $targetGroup instanceof TransferGroup ? $targetGroup : null,
                'targetCondition' => $condition instanceof TransferKingdomConditionObservation
                    ? $condition
                    : null,
            ];
        }

        return $result;
    }

    /** @param Collection<int, TransferKingdomConditionObservation> $rows */
    private function conditionFact(Collection $rows): TransferObservedValue
    {
        $auth = $rows
            ->filter(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative())
            ->values();

        if ($auth->isEmpty()) {
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
        $latest = $auth->first();
        $sameTime = $auth->filter(
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
