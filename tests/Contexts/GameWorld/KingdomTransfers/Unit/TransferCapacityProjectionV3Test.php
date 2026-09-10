<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Unit;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferKingdomCapacityProjection;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class TransferCapacityProjectionV3Test extends TestCase
{
    public function test_projection_separates_observed_game_capacity_from_alliance_reservations(): void
    {
        $projection = new TransferKingdomCapacityProjection(
            kingdomId: '01KINGDOM',
            state: TransferRequirementState::Met,
            officialTotalCapacity: 55,
            officialOrdinaryInviteCapacity: 35,
            officialTransferOpenCapacity: 20,
            ordinaryInvitesUsed: 20,
            transferOpensUsed: 2,
            specialInvitesAvailable: 3,
            plannedOrdinaryInviteReservations: 5,
            plannedTransferOpenReservations: 4,
            plannedSpecialInviteAllocations: 2,
            sourceType: TransferSourceType::InGame,
            sourceReference: 'KingShot transfer screen',
            observedAt: CarbonImmutable::parse('2026-09-07T12:00:00Z'),
        );

        self::assertSame(33, $projection->totalRemaining()->value);
        self::assertSame(24, $projection->totalRemaining(true)->value);
        self::assertSame(15, $projection->ordinaryInviteRemaining()->value);
        self::assertSame(10, $projection->ordinaryInviteRemaining(true)->value);
        self::assertSame(18, $projection->transferOpenRemaining()->value);
        self::assertSame(14, $projection->transferOpenRemaining(true)->value);
        self::assertSame(3, $projection->specialInviteRemaining()->value);
        self::assertSame(1, $projection->specialInviteRemaining(true)->value);
    }

    public function test_missing_observed_inventory_fails_closed_instead_of_becoming_zero_capacity(): void
    {
        $projection = new TransferKingdomCapacityProjection(
            kingdomId: '01KINGDOM',
            state: TransferRequirementState::Met,
            officialTotalCapacity: 55,
            officialOrdinaryInviteCapacity: 35,
            officialTransferOpenCapacity: 20,
            ordinaryInvitesUsed: 0,
            transferOpensUsed: 0,
            specialInvitesAvailable: null,
            plannedOrdinaryInviteReservations: 0,
            plannedTransferOpenReservations: 0,
            plannedSpecialInviteAllocations: 0,
            sourceType: TransferSourceType::InGame,
            sourceReference: 'KingShot transfer screen',
            observedAt: CarbonImmutable::parse('2026-09-07T12:00:00Z'),
        );

        self::assertSame(TransferRequirementState::Unknown, $projection->specialInviteRemaining()->state);
    }
}
