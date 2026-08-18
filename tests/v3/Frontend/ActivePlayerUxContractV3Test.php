<?php

declare(strict_types=1);

namespace Tests\v3\Frontend;

use PHPUnit\Framework\TestCase;

final class ActivePlayerUxContractV3Test extends TestCase
{
    public function test_shell_uses_one_shared_active_player_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $types = $this->source($root.'/resources/js/types/player-context.ts');
        $layout = $this->source($root.'/resources/js/layouts/AppLayout.vue');
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');

        self::assertStringContainsString('export type SharedPlayerContext', $types);
        self::assertStringContainsString('export type PlayerContextFingerprint', $types);
        self::assertStringContainsString('contextFingerprint: PlayerContextFingerprint', $types);
        self::assertStringContainsString('authorityContextVersion: string | null', $types);
        self::assertStringContainsString('activePlayerFrom', $layout);
        self::assertStringContainsString('activePlayerFrom', $switcher);
        self::assertStringContainsString('playerHasCapability', $layout);
        self::assertStringContainsString("requiredCapability: 'recruitment.manage'", $layout);
    }

    public function test_switcher_requests_only_player_activation_and_a_non_authoritative_route_hint(): void
    {
        $root = dirname(__DIR__, 3);
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');

        self::assertStringContainsString('`/players/${playerId}/activate`', $switcher);
        self::assertStringContainsString('{ returnTo: currentPath.value }', $switcher);
        self::assertStringContainsString('preserveState: false', $switcher);
        self::assertStringContainsString('preserveScroll: false', $switcher);
        self::assertStringNotContainsString('alliance_id', $switcher);
        self::assertStringNotContainsString('kingdom_id', $switcher);
        self::assertStringNotContainsString('membership_id', $switcher);
        self::assertStringNotContainsString('capabilities:', $switcher);
    }

    public function test_switcher_exposes_identity_context_and_keyboard_accessibility(): void
    {
        $root = dirname(__DIR__, 3);
        $switcher = $this->source($root.'/resources/js/components/navigation/IdentitySwitcher.vue');

        foreach ([
            'player.alliance?.name',
            'player.alliance.rank',
            'player.alliance.roles',
            'player.kingdomNumber',
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
            'context.authorityContextVersion',
            'createContextAbortController',
            'registerContextDisposer',
            'platformScopedStorageKey',
            'playerScopedStorageKey',
            'contextScopedStorageKey',
            'kingshot:context-freeze',
            'kingshot:context-invalidated',
            'kingshot:context-thaw',
        ] as $expected) {
            self::assertStringContainsString($expected, $isolation);
        }
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
            "X-Game-Context-Version",
            "X-Game-Context-Error",
            'kingshot:authority-context-stale',
            'isAuthorityContextStaleResponse',
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

    public function test_server_projects_display_context_but_remains_authoritative_for_activation(): void
    {
        $root = dirname(__DIR__, 3);
        $middleware = $this->source($root.'/app/Contexts/GameWorld/Players/Http/Middleware/HandleInertiaRequests.php');
        $activation = $this->source($root.'/app/Contexts/GameWorld/Players/Actions/ActivatePlayer.php');
        $controller = $this->source($root.'/app/Contexts/GameWorld/Players/Http/Controllers/ActivatePlayerController.php');

        self::assertStringContainsString('PlayerIdentityContextQuery', $middleware);
        self::assertStringContainsString("'alliance' =>", $middleware);
        self::assertStringContainsString("'roles' =>", $middleware);
        self::assertStringContainsString("'capabilities' =>", $middleware);
        self::assertStringContainsString("'contextFingerprint' =>", $middleware);
        self::assertStringContainsString("'authorityContextVersion' =>", $middleware);
        self::assertStringContainsString("'membershipId' =>", $middleware);

        self::assertStringContainsString("->where('user_id', \$userId)", $activation);
        self::assertStringContainsString('PlayerSwitchRouteResolver', $controller);
        self::assertStringContainsString("\$request->input('returnTo')", $controller);
        self::assertStringContainsString('return redirect()->to($destination);', $controller);
    }

    private function source(string $path): string
    {
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
