<?php

declare(strict_types=1);

namespace Tests\Unit\GameWorld;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use PHPUnit\Framework\TestCase;

final class KingdomRoleVocabularyTest extends TestCase
{
    public function test_default_kingdom_roles_and_permissions_are_game_world_vocabulary_only(): void
    {
        self::assertSame(
            ['kingdom_admin', 'kingdom_event_coordinator', 'kingdom_viewer'],
            array_map(static fn (DefaultKingdomRole $role): string => $role->value, DefaultKingdomRole::cases()),
        );

        self::assertSame('kingdom.roles.manage', KingdomPermission::RoleManage->key());
        self::assertFalse(
            method_exists(DefaultKingdomRole::class, 'permissions'),
            'GameWorld role identity must not embed downstream context permission bundles.',
        );
    }
}
