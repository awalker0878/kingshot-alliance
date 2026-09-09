<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferOfficialRulebook;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

        $sources = array_map(static fn (TransferSourceType $source): string => $source->value, array_values(array_filter(TransferSourceType::cases(), static fn (TransferSourceType $source): bool => $source->isAuthoritative())));
        $conditions = TransferKingdomConditionObservation::query()
            ->selectRaw('DISTINCT ON (kingdom_id) *')
            ->where('alliance_id', $allianceId)->where('transfer_window_id', $windowId)
            ->whereIn('kingdom_id', $kingdomIds)->whereIn('source_type', $sources)
            ->orderBy('kingdom_id')->orderByDesc('observed_at')->orderByDesc('id')->get()->keyBy('kingdom_id');
        $capacityQuery = TransferKingdomCapacityObservation::query()
            ->selectRaw('DISTINCT ON (kingdom_id) *')
            ->where('alliance_id', $allianceId)->where('transfer_window_id', $windowId)
            ->whereIn('kingdom_id', $kingdomIds)->whereIn('source_type', $sources)
            ->orderBy('kingdom_id')->orderByDesc('observed_at')->orderByDesc('id');
        $capacities = (clone $capacityQuery)->get()->keyBy('kingdom_id');
        $reservations = DB::table('transfer_capacity_reservations as commitment')
            ->leftJoinSub($capacityQuery->toBase(), 'capacity', 'capacity.kingdom_id', '=', 'commitment.target_kingdom_id')
            ->where('commitment.alliance_id', $allianceId)->where('commitment.transfer_window_id', $windowId)
            ->whereIn('commitment.target_kingdom_id', $kingdomIds)
            ->where(function (Builder $query): void {
                $query->whereIn('commitment.state', [TransferCapacityReservationState::Planned->value, TransferCapacityReservationState::Reserved->value])
                    ->orWhere(function (Builder $confirmed): void {
                        $confirmed->where('commitment.state', TransferCapacityReservationState::Confirmed->value)
                            ->where(function (Builder $unreflected): void {
                                $unreflected->whereNull('capacity.id')->orWhereNull('commitment.updated_at')->orWhereColumn('commitment.updated_at', '>', 'capacity.observed_at');
                            });
                    });
            })->select(['commitment.target_kingdom_id', 'commitment.bucket'])->selectRaw('COUNT(*) AS total')
            ->groupBy('commitment.target_kingdom_id', 'commitment.bucket')->get()->groupBy('target_kingdom_id');
        $allocations = DB::table('transfer_invitation_allocations as commitment')
            ->leftJoinSub($capacityQuery->toBase(), 'capacity', 'capacity.kingdom_id', '=', 'commitment.target_kingdom_id')
            ->where('commitment.alliance_id', $allianceId)->where('commitment.transfer_window_id', $windowId)
            ->whereIn('commitment.target_kingdom_id', $kingdomIds)
            ->where('commitment.kind', TransferInvitationKind::Special->value)
            ->where(function (Builder $query): void {
                $query->where('commitment.state', TransferInvitationAllocationState::Reserved->value)
                    ->orWhere(function (Builder $issued): void {
                        $issued->whereIn('commitment.state', [TransferInvitationAllocationState::Issued->value, TransferInvitationAllocationState::Accepted->value])
                            ->where(function (Builder $unreflected): void {
                                $unreflected->whereNull('capacity.id')->orWhereNull('commitment.updated_at')->orWhereColumn('commitment.updated_at', '>', 'capacity.observed_at');
                            });
                    });
            })->select('commitment.target_kingdom_id')->selectRaw('COUNT(*) AS total')
            ->groupBy('commitment.target_kingdom_id')->get()->keyBy('target_kingdom_id');

        $result = [];
        foreach ($kingdomIds as $kingdomId) {
            $condition = $conditions->get($kingdomId);
            $capacity = $capacities->get($kingdomId);
            $official = $condition instanceof TransferKingdomConditionObservation && $condition->classification !== null
                ? $this->rules->capacity($condition->classification)
                : null;
            $state = $condition instanceof TransferKingdomConditionObservation
                && $capacity instanceof TransferKingdomCapacityObservation
                && $official !== null
                    ? TransferRequirementState::Met
                    : TransferRequirementState::Unknown;

            $targetReservations = $reservations->get($kingdomId, collect())->keyBy('bucket');
            $result[$kingdomId] = new TransferKingdomCapacityProjection(
                kingdomId: $kingdomId,
                state: $state,
                officialTotalCapacity: $official['total'] ?? null,
                officialOrdinaryInviteCapacity: $official['ordinary_invites'] ?? null,
                officialTransferOpenCapacity: $official['transfer_opens'] ?? null,
                ordinaryInvitesUsed: $capacity?->ordinary_invites_used,
                transferOpensUsed: $capacity?->transfer_opens_used,
                specialInvitesAvailable: $capacity?->special_invites_available,
                plannedOrdinaryInviteReservations: (int) ($targetReservations->get(TransferCapacityBucket::OrdinaryInvite->value)?->total ?? 0),
                plannedTransferOpenReservations: (int) ($targetReservations->get(TransferCapacityBucket::TransferOpen->value)?->total ?? 0),
                plannedSpecialInviteAllocations: (int) ($allocations->get($kingdomId)?->total ?? 0),
                sourceType: $capacity?->source_type,
                sourceReference: $capacity?->source_reference,
                observedAt: $capacity instanceof TransferKingdomCapacityObservation ? CarbonImmutable::instance($capacity->observed_at) : null,
            );
        }

        return $result;
    }
}
