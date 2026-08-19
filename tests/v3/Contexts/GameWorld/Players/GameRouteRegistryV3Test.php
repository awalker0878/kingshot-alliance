<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Services\GameRouteRegistry;
use PHPUnit\Framework\TestCase;

final class GameRouteRegistryV3Test extends TestCase
{
    private GameRouteRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new GameRouteRegistry;
    }

    public function test_navigation_is_projected_from_one_context_policy(): void
    {
        self::assertSame(
            [['key' => 'dashboard', 'href' => '/dashboard', 'icon' => 'dashboard', 'exact' => true]],
            $this->registry->navigation(false, null),
        );

        $governorNavigation = $this->registry->navigation(true, null);
        self::assertContains(
            ['key' => 'events', 'href' => '/events', 'icon' => 'events', 'exact' => false],
            $governorNavigation,
        );
        self::assertNotContains(
            ['key' => 'alliance', 'href' => '/alliance', 'icon' => 'alliance', 'exact' => true],
            $governorNavigation,
        );
    }

    public function test_alliance_navigation_requires_target_alliance_and_effective_capability(): void
    {
        $withoutRecruitment = $this->registry->navigation(true, ['capabilities' => []]);
        self::assertContains(
            ['key' => 'alliance', 'href' => '/alliance', 'icon' => 'alliance', 'exact' => true],
            $withoutRecruitment,
        );
        self::assertNotContains(
            ['key' => 'recruitment', 'href' => '/alliance/recruitment', 'icon' => 'recruitment', 'exact' => false],
            $withoutRecruitment,
        );

        $withRecruitment = $this->registry->navigation(true, [
            'capabilities' => ['recruitment.manage'],
        ]);
        self::assertContains(
            ['key' => 'recruitment', 'href' => '/alliance/recruitment', 'icon' => 'recruitment', 'exact' => false],
            $withRecruitment,
        );
    }

    public function test_collection_routes_and_resource_routes_reconcile_to_safe_registered_parents(): void
    {
        $context = ['capabilities' => ['recruitment.manage']];

        self::assertSame(
            '/alliance/recruitment',
            $this->registry->resolveSwitchDestination('/alliance/recruitment', $context),
        );
        self::assertSame(
            '/alliance/recruitment',
            $this->registry->resolveSwitchDestination('/alliance/recruitment/candidates/01ABC', $context),
        );
        self::assertSame(
            '/alliance/roster',
            $this->registry->resolveSwitchDestination('/alliance/roster/dossiers/01ABC', $context),
        );
        self::assertSame(
            '/events',
            $this->registry->resolveSwitchDestination('/events/01ABC/manage', $context),
        );
    }

    public function test_capability_loss_and_missing_alliance_fail_to_safe_parent(): void
    {
        self::assertSame(
            '/alliance',
            $this->registry->resolveSwitchDestination('/alliance/recruitment', ['capabilities' => []]),
        );
        self::assertSame(
            '/dashboard',
            $this->registry->resolveSwitchDestination('/alliance/roster', null),
        );
    }

    public function test_unknown_or_non_local_destinations_fail_closed_to_dashboard(): void
    {
        self::assertSame(
            '/dashboard',
            $this->registry->resolveSwitchDestination('https://example.com/admin', null),
        );
        self::assertSame(
            '/dashboard',
            $this->registry->resolveSwitchDestination('//example.com/admin', null),
        );
        self::assertSame(
            '/dashboard',
            $this->registry->resolveSwitchDestination('/unknown/private-resource', null),
        );
    }
}
