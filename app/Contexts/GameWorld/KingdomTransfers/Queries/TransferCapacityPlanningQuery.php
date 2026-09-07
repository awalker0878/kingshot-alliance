<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCapacityReservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferInvitationAllocation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferOfficialRulebook;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;
use Carbon\CarbonImmutable;

final readonly class TransferCapacityPlanningQuery
{
    public function __construct(private TransferOfficialRulebook $rules) {}

    /**
     * @param  list<string>  $kingdomIds
     * @return array<string, TransferKingdomCapacityProjection>
     */
    public function forTargets(string $allianceId, string $windowId, array $kingdomIds): array
    {
        $kingdomIds = array_values(array_unique(array_filter($kingdomIds)));
        if ($kingdomIds === []) {
            return [];
        }

        $conditions = TransferKingdomConditionObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereIn('kingdom_id', $kingdomIds)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('kingdom_id');
        $capacities = TransferKingdomCapacityObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereIn('kingdom_id', $kingdomIds)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('kingdom_id');
        $reservations = TransferCapacityReservation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereIn('target_kingdom_id', $kingdomIds)
            ->get();
        $allocations = TransferInvitationAllocation::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereIn('target_kingdom_id', $kingdomIds)
            ->get();

        $result = [];
        foreach ($kingdomIds as $kingdomId) {
            $condition = $conditions->get($kingdomId, collect())
                ->first(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative());
            $capacity = $capacities->get($kingdomId, collect())
                ->first(static fn (TransferKingdomCapacityObservation $row): bool => $row->source_type->isAuthoritative());
            $official = $condition instanceof TransferKingdomConditionObservation && $condition->classification !== null
                ? $this->rules->capacity($condition->classification)
                : null;
            $state = $condition instanceof TransferKingdomConditionObservation
                && $capacity instanceof TransferKingdomCapacityObservation
                && $official !== null
                    ? TransferRequirementState::Met
                    : TransferRequirementState::Unknown;

            $targetReservations = $reservations->where('target_kingdom_id', $kingdomId)
                ->filter(fn (TransferCapacityReservation $row): bool => $this->reservationStillConsumes($row, $capacity));
            $targetAllocations = $allocations->where('target_kingdom_id', $kingdomId)
                ->filter(fn (TransferInvitationAllocation $row): bool => $this->allocationStillConsumes($row, $capacity));

            $result[$kingdomId] = new TransferKingdomCapacityProjection(
                kingdomId: $kingdomId,
                state: $state,
                officialTotalCapacity: $official['total'] ?? null,
                officialOrdinaryInviteCapacity: $official['ordinary_invites'] ?? null,
                officialTransferOpenCapacity: $official['transfer_opens'] ?? null,
                ordinaryInvitesUsed: $capacity?->ordinary_invites_used,
                transferOpensUsed: $capacity?->transfer_opens_used,
                specialInvitesAvailable: $capacity?->special_invites_available,
                plannedOrdinaryInviteReservations: $targetReservations->where('bucket', TransferCapacityBucket::OrdinaryInvite)->count(),
                plannedTransferOpenReservations: $targetReservations->where('bucket', TransferCapacityBucket::TransferOpen)->count(),
                plannedSpecialInviteAllocations: $targetAllocations->where('kind', TransferInvitationKind::Special)->count(),
                sourceType: $capacity?->source_type,
                sourceReference: $capacity?->source_reference,
                observedAt: $capacity instanceof TransferKingdomCapacityObservation ? CarbonImmutable::instance($capacity->observed_at) : null,
            );
        }

        return $result;
    }

    private function reservationStillConsumes(
        TransferCapacityReservation $reservation,
        mixed $capacity,
    ): bool {
        if (! $reservation->state->consumesPlannedCapacity()) {
            return false;
        }

        if ($reservation->state !== TransferCapacityReservationState::Confirmed) {
            return true;
        }

        return ! $this->isReflectedByCapacityObservation($reservation->getAttribute('updated_at'), $capacity);
    }

    private function allocationStillConsumes(
        TransferInvitationAllocation $allocation,
        mixed $capacity,
    ): bool {
        if (! $allocation->state->consumesPlannedInventory()) {
            return false;
        }

        if (! in_array($allocation->state, [TransferInvitationAllocationState::Issued, TransferInvitationAllocationState::Accepted], true)) {
            return true;
        }

        return ! $this->isReflectedByCapacityObservation($allocation->getAttribute('updated_at'), $capacity);
    }

    private function isReflectedByCapacityObservation(
        mixed $commitmentUpdatedAt,
        mixed $capacity,
    ): bool {
        if (! $capacity instanceof TransferKingdomCapacityObservation || $commitmentUpdatedAt === null) {
            return false;
        }

        return CarbonImmutable::parse((string) $commitmentUpdatedAt)
            ->lessThanOrEqualTo(CarbonImmutable::instance($capacity->observed_at));
    }
}
