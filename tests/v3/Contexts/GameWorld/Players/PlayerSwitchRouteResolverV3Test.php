<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Services\PlayerSwitchRouteResolver;
use PHPUnit\Framework\TestCase;

final class PlayerSwitchRouteResolverV3Test extends TestCase
{
    private PlayerSwitchRouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PlayerSwitchRouteResolver;
    }

    public function test_platform_and_player_collection_routes_can_survive_a_switch(): void
    {
        self::assertSame('/dashboard', $this->resolver->resolve('/dashboard', null));
        self::assertSame('/profile', $this->resolver->resolve('/profile', null));
        self::assertSame('/events', $this->resolver->resolve('/events', null));
    }

    public function test_alliance_collection_routes_require_a_target_alliance(): void
    {
        $context = ['capabilities' => []];

        self::assertSame('/alliance/roster', $this->resolver->resolve('/alliance/roster', $context));
        self::assertSame('/dashboard', $this->resolver->resolve('/alliance/roster', null));
    }

    public function test_capability_routes_are_reconciled_against_the_target_context(): void
    {
        self::assertSame(
            '/alliance/recruitment',
            $this->resolver->resolve('/alliance/recruitment', ['capabilities' => ['recruitment.manage']]),
        );
        self::assertSame(
            '/alliance',
            $this->resolver->resolve('/alliance/recruitment', ['capabilities' => []]),
        );
    }

    public function test_resource_specific_routes_collapse_to_safe_collection_parents(): void
    {
        $context = ['capabilities' => ['recruitment.manage']];

        self::assertSame(
            '/alliance/recruitment',
            $this->resolver->resolve('/alliance/recruitment/candidates/01ABC', $context),
        );
        self::assertSame(
            '/alliance/roster',
            $this->resolver->resolve('/alliance/roster/dossiers/01ABC', $context),
        );
        self::assertSame('/events', $this->resolver->resolve('/events/01ABC/manage', $context));
    }

    public function test_unknown_or_non_local_destinations_fail_closed_to_dashboard(): void
    {
        self::assertSame('/dashboard', $this->resolver->resolve('https://example.com/admin', null));
        self::assertSame('/dashboard', $this->resolver->resolve('//example.com/admin', null));
        self::assertSame('/dashboard', $this->resolver->resolve('/unknown/private-resource', null));
    }
}
