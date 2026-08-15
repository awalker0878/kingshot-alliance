<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Domain\Authorization\Enums\PermissionKey;
use PHPUnit\Framework\TestCase;

final class AllianceRankPermissionsTest extends TestCase
{
    public function test_rank_hierarchy_matches_kingshot_r1_through_r5(): void
    {
        self::assertSame(100, AllianceRank::R1->level());
        self::assertSame(200, AllianceRank::R2->level());
        self::assertSame(300, AllianceRank::R3->level());
        self::assertSame(400, AllianceRank::R4->level());
        self::assertSame(500, AllianceRank::R5->level());
        self::assertTrue(AllianceRank::R5->isLeader());
        self::assertTrue(AllianceRank::R4->isOfficer());
    }

    public function test_r5_and_r4_receive_alliance_event_operations_without_kingdom_event_authority(): void
    {
        $permissions = new AllianceRankPermissions;

        foreach ([AllianceRank::R5, AllianceRank::R4] as $rank) {
            self::assertTrue($permissions->allows($rank, PermissionKey::EventAllianceCreate));
            self::assertTrue($permissions->allows($rank, PermissionKey::EventAllianceManage));
            self::assertFalse($permissions->allows($rank, PermissionKey::EventKingdomCreate));
            self::assertFalse($permissions->allows($rank, PermissionKey::EventKingdomManage));
        }
    }

    public function test_lower_ranks_can_view_alliance_events_but_do_not_manage_them(): void
    {
        $permissions = new AllianceRankPermissions;

        foreach ([AllianceRank::R1, AllianceRank::R2, AllianceRank::R3] as $rank) {
            self::assertTrue($permissions->allows($rank, PermissionKey::EventAllianceView));
            self::assertFalse($permissions->allows($rank, PermissionKey::EventAllianceCreate));
            self::assertFalse($permissions->allows($rank, PermissionKey::EventAllianceManage));
        }
    }

    public function test_default_roles_are_specialist_roles_not_game_ranks(): void
    {
        self::assertSame(
            ['recruiter', 'event_coordinator', 'content_manager'],
            array_map(static fn (DefaultAllianceRole $role): string => $role->value, DefaultAllianceRole::cases()),
        );

        $eventCoordinatorKeys = array_map(
            static fn ($permission): string => $permission->key(),
            DefaultAllianceRole::EventCoordinator->permissions(),
        );

        self::assertContains(PermissionKey::EventAllianceManage->value, $eventCoordinatorKeys);
        self::assertNotContains(PermissionKey::EventKingdomManage->value, $eventCoordinatorKeys);
    }
}
