<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Access\Contracts\Permission;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
        $mutationAuthority = file_get_contents(dirname(__DIR__, 2).'/app/Contexts/Alliance/Access/Services/AllianceMutationAuthority.php');

        self::assertContains('player_id', $membership->getFillable());
        self::assertNotContains('user_id', $membership->getFillable());
        self::assertIsString($authorization);
        self::assertStringContainsString('activeMembership(Player $player, Alliance $alliance)', $authorization);
        self::assertStringContainsString('allows(Player $player, Alliance $alliance, AlliancePermission $permission)', $authorization);
        self::assertStringNotContainsString('User $user', $authorization);
        self::assertIsString($mutationAuthority);
        self::assertStringContainsString('AlliancePermission $permission', $mutationAuthority);
        self::assertStringContainsString('acquireActiveScope(Player $actor, Alliance $alliance)', $mutationAuthority);
    }

    public function test_alliance_access_has_no_downstream_permission_vocabulary(): void
    {
        $root = dirname(__DIR__, 2).'/app/Contexts/Alliance/Access';
        $forbidden = [
            'NamedPermission',
            'events.player.',
            'events.alliance.',
            'events.kingdom.',
            'contributions.manage',
            'kingdoms.manage',
            'App\\Contexts\\Operations\\',
            'App\\Contexts\\Intelligence\\',
        ];

        foreach ($this->phpFiles($root) as $file) {
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);

            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $file->getPathname().' must contain only Alliance-owned permission semantics.',
                );
            }
        }

        self::assertFalse(
            method_exists(DefaultAllianceRole::class, 'permissions'),
            'Alliance specialist-role identity must not embed downstream permission bundles.',
        );
    }

    public function test_downstream_contexts_own_their_alliance_permission_interpretation(): void
    {
        $root = dirname(__DIR__, 2).'/app/Contexts';

        foreach (['Operations', 'Intelligence'] as $context) {
            foreach ($this->phpFiles($root.'/'.$context) as $file) {
                if (str_contains($file->getPathname(), '/Access/Services/')) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                self::assertIsString($source);
                self::assertStringNotContainsString(
                    'App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization',
                    $source,
                    $file->getPathname().' must use its context-owned Alliance authorization policy.',
                );
                self::assertStringNotContainsString(
                    'App\\Contexts\\Alliance\\Access\\Services\\AllianceMutationAuthority',
                    $source,
                    $file->getPathname().' must use its context-owned Alliance mutation policy.',
                );
            }
        }
    }

    public function test_alliance_context_does_not_reference_the_global_v1_permission_catalogue(): void
    {
        $root = dirname(__DIR__, 2).'/app/Contexts/Alliance';

        foreach ($this->phpFiles($root) as $file) {
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString(
                'App\\Domain\\Authorization\\Enums\\PermissionKey',
                $source,
                $file->getPathname().' must use Alliance-owned permissions.',
            );
        }
    }

    /** @return list<SplFileInfo> */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
