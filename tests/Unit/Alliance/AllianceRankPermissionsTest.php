<?php

declare(strict_types=1);

namespace Tests\Unit\Alliance;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Services\AllianceDefaultRolePermissions;
use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
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

    public function test_rank_permissions_are_alliance_local_only(): void
    {
        $permissions = new AllianceRankPermissions;

        foreach ([AllianceRank::R1, AllianceRank::R2, AllianceRank::R3] as $rank) {
            self::assertSame([AlliancePermission::View], $permissions->for($rank));
        }

        self::assertSame([
            AlliancePermission::View,
            AlliancePermission::MembershipManage,
            AlliancePermission::InvitationManage,
        ], $permissions->for(AllianceRank::R4));

        self::assertSame([
            AlliancePermission::View,
            AlliancePermission::Manage,
            AlliancePermission::MembershipManage,
            AlliancePermission::RoleManage,
            AlliancePermission::InvitationManage,
            AlliancePermission::ContentManage,
            AlliancePermission::RecruitmentManage,
        ], $permissions->for(AllianceRank::R5));
    }

    public function test_default_alliance_roles_are_identity_with_alliance_local_grants_only(): void
    {
        self::assertSame(
            ['recruiter', 'event_coordinator', 'content_manager'],
            array_map(static fn (DefaultAllianceRole $role): string => $role->value, DefaultAllianceRole::cases()),
        );
        self::assertFalse(method_exists(DefaultAllianceRole::class, 'permissions'));

        $permissions = new AllianceDefaultRolePermissions;
        self::assertSame([
            AlliancePermission::InvitationManage,
            AlliancePermission::RecruitmentManage,
        ], $permissions->for(DefaultAllianceRole::Recruiter));
        self::assertSame([], $permissions->for(DefaultAllianceRole::EventCoordinator));
        self::assertSame([
            AlliancePermission::ContentManage,
        ], $permissions->for(DefaultAllianceRole::ContentManager));
    }
}
