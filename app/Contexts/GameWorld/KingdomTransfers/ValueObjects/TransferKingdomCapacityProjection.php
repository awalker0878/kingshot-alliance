<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Carbon\CarbonImmutable;

final readonly class TransferKingdomCapacityProjection
{
    public function __construct(
        public string $kingdomId,
        public TransferRequirementState $state,
        public ?int $officialTotalCapacity,
        public ?int $officialOrdinaryInviteCapacity,
        public ?int $officialTransferOpenCapacity,
        public ?int $ordinaryInvitesUsed,
        public ?int $transferOpensUsed,
        public ?int $specialInvitesAvailable,
        public int $plannedOrdinaryInviteReservations,
        public int $plannedTransferOpenReservations,
        public int $plannedSpecialInviteAllocations,
        public ?TransferSourceType $sourceType,
        public ?string $sourceReference,
        public ?CarbonImmutable $observedAt,
    ) {}

    public function totalRemaining(bool $afterPlanning = false): TransferObservedValue
    {
        if (! $this->hasObservedCapacity()) {
            return $this->unresolved();
        }
        $remaining = max(0, (int) $this->officialTotalCapacity - (int) $this->ordinaryInvitesUsed - (int) $this->transferOpensUsed);
        if ($afterPlanning) {
            $remaining = max(0, $remaining - $this->plannedOrdinaryInviteReservations - $this->plannedTransferOpenReservations);
        }

        return $this->resolved($remaining);
    }

    public function ordinaryInviteRemaining(bool $afterPlanning = false): TransferObservedValue
    {
        if (! $this->hasObservedCapacity()) {
            return $this->unresolved();
        }
        $remaining = max(0, (int) $this->officialOrdinaryInviteCapacity - (int) $this->ordinaryInvitesUsed);
        if ($afterPlanning) {
            $remaining = max(0, $remaining - $this->plannedOrdinaryInviteReservations);
        }

        return $this->resolved($remaining);
    }

    public function transferOpenRemaining(bool $afterPlanning = false): TransferObservedValue
    {
        if (! $this->hasObservedCapacity()) {
            return $this->unresolved();
        }
        $remaining = max(0, (int) $this->officialTransferOpenCapacity - (int) $this->transferOpensUsed);
        if ($afterPlanning) {
            $remaining = max(0, $remaining - $this->plannedTransferOpenReservations);
        }

        return $this->resolved($remaining);
    }

    public function specialInviteRemaining(bool $afterPlanning = false): TransferObservedValue
    {
        if ($this->state !== TransferRequirementState::Met || $this->specialInvitesAvailable === null) {
            return $this->unresolved();
        }
        $remaining = $this->specialInvitesAvailable;
        if ($afterPlanning) {
            $remaining = max(0, $remaining - $this->plannedSpecialInviteAllocations);
        }

        return $this->resolved($remaining);
    }

    private function hasObservedCapacity(): bool
    {
        return $this->state === TransferRequirementState::Met
            && $this->officialTotalCapacity !== null
            && $this->officialOrdinaryInviteCapacity !== null
            && $this->officialTransferOpenCapacity !== null
            && $this->ordinaryInvitesUsed !== null
            && $this->transferOpensUsed !== null;
    }

    private function resolved(int $value): TransferObservedValue
    {
        return new TransferObservedValue(
            TransferRequirementState::Met,
            $value,
            $this->sourceType,
            $this->sourceReference,
            $this->observedAt,
        );
    }

    private function unresolved(): TransferObservedValue
    {
        return new TransferObservedValue(
            $this->state === TransferRequirementState::Met ? TransferRequirementState::Unknown : $this->state,
            null,
            $this->sourceType,
            $this->sourceReference,
            $this->observedAt,
            null,
            'Target transfer capacity has not been verified from an authoritative current source.',
        );
    }
}
