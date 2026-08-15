<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Access\Contracts\Permission;
use PHPUnit\Framework\TestCase;

final class ArchitectureV2AllianceTest extends TestCase
{
    public function test_alliance_permissions_are_owned_by_the_alliance_context(): void
    {
        foreach (AlliancePermission::cases() as $permission) {
            self::assertInstanceOf(Permission::class, $permission);
            self::assertSame($permission->value, $permission->key());
        }

        self::assertSame([
            'alliance.view',
            'alliance.manage',
            'membership.manage',
            'roles.manage',
            'invitations.manage',
            'content.manage',
            'recruitment.manage',
        ], array_map(
            static fn (AlliancePermission $permission): string => $permission->key(),
            AlliancePermission::cases(),
        ));
    }

    public function test_alliance_authority_is_scoped_to_player_membership_not_user(): void
    {
        $membership = new AllianceMembership;
        $authorization = file_get_contents(dirname(__DIR__, 2).'/app/Contexts/Alliance/Access/Services/AllianceAuthorization.php');

        self::assertContains('player_id', $membership->getFillable());
        self::assertNotContains('user_id', $membership->getFillable());
        self::assertIsString($authorization);
        self::assertStringContainsString('activeMembership(Player $player, Alliance $alliance)', $authorization);
        self::assertStringContainsString('allows(Player $player, Alliance $alliance, AlliancePermission $permission)', $authorization);
        self::assertStringNotContainsString('User $user', $authorization);
    }

    public function test_alliance_context_does_not_reference_the_global_v1_permission_catalogue(): void
    {
        $root = dirname(__DIR__, 2).'/app/Contexts/Alliance';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo === false || $file->isFile() === false || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString(
                'App\\Domain\\Authorization\\Enums\\PermissionKey',
                $source,
                $file->getPathname().' must use Alliance-owned permissions or Shared permission contracts.',
            );
        }
    }
}
