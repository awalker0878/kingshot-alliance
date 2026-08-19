<?php

declare(strict_types=1);

namespace Tests\v3\Frontend;

use PHPUnit\Framework\TestCase;

final class ActivePlayerUxContractV3Test extends TestCase
{
    public function test_shell_uses_one_shared_game_context_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $types = $this->source($root.'/resources/js/types/game-context.ts');
        $context = $this->source($root.'/resources/js/composables/useGameContext.ts');
        $layout = $this->source($root.'/resources/js/layouts/AppLayout.vue');

        self::assertStringContainsString('export type SharedGameContext', $types);
        self::assertStringContainsString('export type ActiveGameContext', $types);
        self::assertStringContainsString('authorityVersion: string', $types);
        self::assertStringContainsString('fingerprint: GameContextFingerprint', $types);
        self::assertStringContainsString('useGameContext()', $context);
        self::assertStringContainsString('gameContext', $context);
        self::assertStringContainsString('viewer', $context);
        self::assertStringContainsString('navigation: computed', $context);
        self::assertStringContainsString('useGameContext', $layout);
        self::assertStringNotContainsString('hasPlayerAlliance', $layout);
        self::assertStringNotContainsString('playerAllianceName', $layout);
        self::assertStringNotContainsString('requiredCapability', $layout);
        self::assertStringNotContainsString('allianceScoped', $layout);
    }

    public function test_switcher_requests_only_governor_activation_and_a_non_authoritative_route_hint(): void
    {
        $root = dirname(__DIR__, 3);
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');

        self::assertStringContainsString('`/players/${governorId}/activate`', $switcher);
        self::assertStringContainsString('{ returnTo: currentPath.value }', $switcher);
        self::assertStringContainsString('preserveState: false', $switcher);
        self::assertStringContainsString('preserveScroll: false', $switcher);
        self::assertStringNotContainsString('alliance_id', $switcher);
        self::assertStringNotContainsString('kingdom_id', $switcher);
        self::assertStringNotContainsString('membership_id', $switcher);
        self::assertStringNotContainsString('capabilities:', $switcher);
    }

    public function test_switcher_exposes_governor_context_and_keyboard_accessibility(): void
    {
        $root = dirname(__DIR__, 3);
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');

        foreach ([
            'governor.alliance?.name',
            'governor.alliance.rank',
            'governor.alliance.roles',
            'governor.kingdom.number',
            'aria-live="polite"',
            "focusOption('next')",
            "focusOption('previous')",
            "focusOption('first')",
            "focusOption('last')",
            '@keydown.esc.prevent="close"',
        ] as $expected) {
            self::assertStringContainsString($expected, $switcher);
        }
    }

    public function test_switch_transition_freezes_and_invalidates_old_context_state(): void
    {
        $root = dirname(__DIR__, 3);
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');
        $isolation = $this->source($root.'/resources/js/identity/context-isolation.ts');

        foreach ([
            'activeContextKey',
            'beginContextTransition',
            'completeContextTransition',
            'cancelContextTransition',
        ] as $expected) {
            self::assertStringContainsString($expected, $switcher);
        }

        foreach ([
            'context.active?.authorityVersion',
            'createContextAbortController',
            'registerContextDisposer',
            'platformScopedStorageKey',
            'governorScopedStorageKey',
            'contextScopedStorageKey',
            'kingshot:context-freeze',
            'kingshot:context-invalidated',
            'kingshot:context-thaw',
        ] as $expected) {
            self::assertStringContainsString($expected, $isolation);
        }

        self::assertStringNotContainsString('playerScopedStorageKey', $isolation);
    }

    public function test_stale_authority_context_is_transported_and_recovered_centrally(): void
    {
        $root = dirname(__DIR__, 3);
        $app = $this->source($root.'/resources/js/app.ts');
        $authority = $this->source($root.'/resources/js/identity/authority-context.ts');
        $guard = $this->source($root.'/app/Contexts/GameWorld/Players/Http/Middleware/RequireCurrentPlayerContextVersion.php');

        foreach ([
            'defaults:',
            'visitOptions:',
            'authorityContextHeaders(options.headers)',
            "router.on('httpException'",
            'installFetchInterceptor()',
            "router.visit('/dashboard'",
            'preserveState: false',
            'preserveScroll: false',
        ] as $expected) {
            self::assertStringContainsString($expected, $app);
        }

        foreach ([
            'X-Game-Context-Version',
            'X-Game-Context-Error',
            'kingshot:authority-context-stale',
            'isAuthorityContextStaleResponse',
            'props.gameContext',
        ] as $expected) {
            self::assertStringContainsString($expected, $authority);
        }

        foreach ([
            'CONTEXT_STALE',
            'hash_equals',
            'findOwnedByUser',
            'KingdomAuthorityFactsQuery',
            "'players.activate'",
            "'profile.'",
        ] as $expected) {
            self::assertStringContainsString($expected, $guard);
        }
    }

    public function test_server_projects_active_authority_and_server_owned_navigation(): void
    {
        $root = dirname(__DIR__, 3);
        $middleware = $this->source($root.'/app/Contexts/GameWorld/Players/Http/Middleware/HandleInertiaRequests.php');
        $activation = $this->source($root.'/app/Contexts/GameWorld/Players/Actions/ActivatePlayer.php');
        $controller = $this->source($root.'/app/Contexts/GameWorld/Players/Http/Controllers/ActivatePlayerController.php');
        $registry = $this->source($root.'/app/Contexts/GameWorld/Players/Services/GameRouteRegistry.php');

        foreach ([
            "'viewer' =>",
            "'gameContext' =>",
            "'governors' =>",
            "'active' =>",
            "'alliance' =>",
            "'kingdom' =>",
            "'capabilities' =>",
            "'fingerprint' =>",
            "'authorityVersion' =>",
            "'navigation' =>",
            'GameRouteRegistry',
        ] as $expected) {
            self::assertStringContainsString($expected, $middleware);
        }

        self::assertStringNotContainsString("'playerContext' =>", $middleware);
        self::assertStringContainsString("->where('user_id', \$userId)", $activation);
        self::assertStringContainsString('GameRouteRegistry', $controller);
        self::assertStringContainsString("\$request->input('returnTo')", $controller);
        self::assertStringContainsString('return redirect()->to($destination);', $controller);
        self::assertStringContainsString('function navigation(', $registry);
        self::assertStringContainsString('function resolveSwitchDestination(', $registry);
    }

    private function source(string $path): string
    {
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
