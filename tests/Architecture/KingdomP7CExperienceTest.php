<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomP7CExperienceTest extends TestCase
{
    public function test_sharing_surfaces_preserve_routes_and_shared_shell(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/kingdoms.php');
        $controller = file_get_contents($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomIntelligenceSharingController.php');
        $member = file_get_contents($root.'/resources/js/pages/Alliance/KingdomSharing.vue');
        $manage = file_get_contents($root.'/resources/js/pages/Alliance/KingdomSharingManage.vue');
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($member);
        self::assertIsString($manage);

        foreach ([
            '/alliance/kingdom-sharing',
            '/alliance/kingdom-sharing/manage',
            '/alliance/kingdom-sharing/invitations',
            '/alliance/kingdom-sharing/invitations/accept',
            '/alliance/kingdom-sharing/invitations/decline',
            '/alliance/kingdom-sharing/{share}/revoke',
            '/alliance/kingdom-sharing/{share}/leave',
            '/alliance/kingdom-sharing/{share}/targets/{tracking}',
            '/alliance/kingdom-sharing/{share}/targets/{target}/remove',
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        self::assertStringContainsString("Route::middleware('password.confirm')->group", $routes);
        self::assertStringContainsString("'user' => [", $controller);
        foreach ([$member, $manage] as $source) {
            self::assertStringContainsString('AppLayout', $source);
            self::assertStringNotContainsString('<main', $source);
            self::assertStringContainsString('kingdomP7C.', $source);
        }
    }

    public function test_visual_mockup_does_not_expand_sharing_scope(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js/pages/Alliance/';
        $source = file_get_contents($root.'KingdomSharing.vue').file_get_contents($root.'KingdomSharingManage.vue');
        self::assertIsString($source);
        foreach ([
            'Public Links',
            'People with Access',
            'Access Matrix',
            'Share Activity',
            'Export Kingdom Data',
            'Create Share',
            'Share Settings',
            'Public Link',
            'QR Code',
            'Threat Score',
            'Share All',
            'Bulk Share',
        ] as $mockupOnly) {
            self::assertStringNotContainsString($mockupOnly, $source);
        }
    }
}
