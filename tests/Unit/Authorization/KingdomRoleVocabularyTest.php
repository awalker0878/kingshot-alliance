<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use PHPUnit\Framework\TestCase;

final class KingdomRoleVocabularyTest extends TestCase
{
    public function test_default_kingdom_roles_have_explicit_least_privilege_bundles(): void
    {
        self::assertSame(
            ['kingdom_admin', 'kingdom_event_coordinator', 'kingdom_viewer'],
            array_map(static fn (DefaultKingdomRole $role): string => $role->value, DefaultKingdomRole::cases()),
        );

        self::assertContains(PermissionKey::KingdomRoleManage, DefaultKingdomRole::Administrator->permissions());
        self::assertContains(PermissionKey::EventKingdomManage, DefaultKingdomRole::Administrator->permissions());
        self::assertNotContains(PermissionKey::KingdomRoleManage, DefaultKingdomRole::EventCoordinator->permissions());
        self::assertSame([PermissionKey::EventKingdomView], DefaultKingdomRole::Viewer->permissions());
    }

    public function test_alliance_ranks_never_grant_kingdom_role_management(): void
    {
        $permissions = new AllianceRankPermissions;

        foreach (AllianceRank::cases() as $rank) {
            self::assertFalse($permissions->allows($rank, PermissionKey::KingdomRoleManage));
        }
    }
}
