<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Presenters;

use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;

/** Transport shaping only; owner rules and authorized reads remain outside the presenter. */
final class TransferManagementPresenter
{
    /** @return array<string,mixed> */
    public function plan(TransferPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'label' => (string) $plan->label,
            'homeKingdom' => (string) $plan->homeKingdom->number,
            'state' => $plan->state->value,
            'createdAt' => $plan->created_at?->toIso8601String(),
            'window' => $this->window($plan->window),
        ];
    }

    /** @return array<string,mixed> */
    public function window(TransferWindow $window): array
    {
        return [
            'id' => (string) $window->id,
            'label' => $window->label,
            'phase' => $window->phaseAt(now('UTC'))->value,
            'preTransferStartsAt' => $window->pre_transfer_starts_at->toIso8601String(),
            'invitationalStartsAt' => $window->invitational_starts_at->toIso8601String(),
            'transferOpensAt' => $window->transfer_opens_at->toIso8601String(),
            'endsAt' => $window->ends_at->toIso8601String(),
            'sourceType' => $window->source_type->value,
            'sourceReference' => $window->source_reference,
            'observedAt' => $window->observed_at->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public function cohort(TransferCohort $cohort, bool $private): array
    {
        $row = [
            'name' => $cohort->name,
            'direction' => $cohort->direction->value,
            'destinationKingdom' => $cohort->destinationKingdom === null ? null : (string) $cohort->destinationKingdom->number,
            'coordinator' => $cohort->coordinator === null ? null : ['name' => $cohort->coordinator->current_name],
        ];
        if ($private) {
            $row['id'] = (string) $cohort->id;
            $row['state'] = $cohort->state->value;
            $row['coordinatorPlayerId'] = $cohort->coordinator_player_id;
            $row['managerNotes'] = $cohort->manager_notes;
        }

        return $row;
    }

    /** @return array<string,mixed> */
    public function officialGroup(TransferGroup $group): array
    {
        return [
            'id' => (string) $group->id,
            'officialLabel' => $group->official_label,
            'revision' => $group->revision,
            'kingdoms' => $group->kingdoms->map(static fn ($kingdom): array => [
                'id' => (string) $kingdom->id,
                'number' => (string) $kingdom->number,
            ])->all(),
            'sourceType' => $group->source_type->value,
            'sourceReference' => $group->source_reference,
            'observedAt' => $group->observed_at->toIso8601String(),
            'supersededAt' => $group->superseded_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public function condition(TransferKingdomConditionObservation $condition): array
    {
        return [
            'id' => (string) $condition->id,
            'kingdom' => (string) $condition->kingdom->number,
            'powerCap' => $condition->power_cap,
            'classification' => $condition->classification?->value,
            'heroGeneration' => $condition->hero_generation,
            'truegoldLevel' => $condition->truegold_level,
            'characterAgeThresholdDays' => $condition->character_age_threshold_days,
            'sourceType' => $condition->source_type->value,
            'sourceReference' => $condition->source_reference,
            'observedAt' => $condition->observed_at->toIso8601String(),
            'isCorrection' => $condition->is_correction,
        ];
    }

    /** @return array<string,mixed> */
    public function capacity(TransferKingdomCapacityObservation $capacity): array
    {
        return [
            'id' => (string) $capacity->id,
            'kingdom' => (string) $capacity->kingdom->number,
            'ordinaryInvitesUsed' => $capacity->ordinary_invites_used,
            'transferOpensUsed' => $capacity->transfer_opens_used,
            'specialInvitesAvailable' => $capacity->special_invites_available,
            'sourceType' => $capacity->source_type->value,
            'sourceReference' => $capacity->source_reference,
            'observedAt' => $capacity->observed_at->toIso8601String(),
            'isCorrection' => $capacity->is_correction,
        ];
    }

    /** @return array<string,mixed> */
    public function participant(TransferParticipant $participant, bool $private): array
    {
        $row = [
            'id' => (string) $participant->id,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'name' => $participant->observed_name,
            'gamePlayerId' => $participant->game_player_id,
            'sourceKingdom' => $participant->sourceKingdom === null ? null : (string) $participant->sourceKingdom->number,
            'destinationKingdom' => $participant->destinationKingdom === null ? null : (string) $participant->destinationKingdom->number,
            'player' => ['id' => (string) $participant->player_id, 'name' => $participant->player->current_name],
            'cohort' => $participant->cohort === null ? null : $this->cohort($participant->cohort, false),
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
            'completedAt' => $participant->completion?->completed_at->toIso8601String(),
        ];
        if ($private) {
            $row['rosterEntryId'] = $participant->roster_entry_id;
            $row['transferCohortId'] = $participant->transfer_cohort_id;
            $row['managerNotes'] = $participant->manager_notes;
        }

        return $row;
    }
}
