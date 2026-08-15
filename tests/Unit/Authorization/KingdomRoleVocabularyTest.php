<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use PHPUnit\Framework\TestCase;

final class KingdomRoleVocabularyTest extends TestCase
{
    public function test_default_kingdom_roles_and_permissions_have_context_owned_vocabulary(): void
    {
        self::assertSame(
            ['kingdom_admin', 'kingdom_event_coordinator', 'kingdom_viewer'],
            array_map(static fn (DefaultKingdomRole $role): string => $role->value, DefaultKingdomRole::cases()),
        );

        self::assertSame('kingdom.roles.manage', KingdomPermission::RoleManage->key());
        self::assertSame('events.kingdom.view', OperationsPermission::EventKingdomView->key());
        self::assertSame('events.kingdom.create', OperationsPermission::EventKingdomCreate->key());
        self::assertSame('events.kingdom.manage', OperationsPermission::EventKingdomManage->key());

        self::assertFalse(
            method_exists(DefaultKingdomRole::class, 'permissions'),
            'GameWorld role vocabulary must not embed Operations permission bundles.',
        );
    }

    public function test_alliance_ranks_never_grant_kingdom_role_management(): void
    {
        $permissions = new AllianceRankPermissions;

        foreach (AllianceRank::cases() as $rank) {
            self::assertFalse($permissions->allows($rank, KingdomPermission::RoleManage));
        }
    }
}
