import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const write = (file, content) => {
  const target = path.join(root, file);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, content);
};
const remove = (file) => {
  const target = path.join(root, file);
  if (fs.existsSync(target)) fs.rmSync(target);
};

const gameContextTs = `import type { ComputedRef } from 'vue';

export type RoleIdentity = {
  key: string;
  name: string;
};

export type GovernorAllianceIdentity = {
  id: string;
  membershipId: string;
  name: string;
  rank: string;
  roles: RoleIdentity[];
};

export type GovernorIdentity = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdom: {
    id: string;
    number: number | null;
  };
  alliance: GovernorAllianceIdentity | null;
};

export type GameContextFingerprint = {
  version: 1;
  key: string;
  playerId: string;
  kingdomId: string;
  kingdomNumber: number | null;
  allianceId: string | null;
  membershipId: string | null;
};

export type ActiveGameContext = {
  governor: GovernorIdentity;
  kingdom: {
    id: string;
    number: number | null;
    capabilities: string[];
  };
  alliance: (GovernorAllianceIdentity & { capabilities: string[] }) | null;
  fingerprint: GameContextFingerprint;
  authorityVersion: string;
};

export type GameNavigationIcon =
  | 'dashboard'
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'contributions'
  | 'kingdom'
  | 'transfers'
  | 'integrations';

export type GameNavigationItem = {
  key: GameNavigationIcon;
  href: string;
  icon: GameNavigationIcon;
  exact: boolean;
};

export type SharedGameContext = {
  version: 1;
  governors: GovernorIdentity[];
  active: ActiveGameContext | null;
  navigation: GameNavigationItem[];
};

export type SharedViewer = {
  id: number;
  name: string;
  email: string;
} | null;

export const EMPTY_GAME_CONTEXT: SharedGameContext = {
  version: 1,
  governors: [],
  active: null,
  navigation: [],
};

export type GameContextView = {
  context: ComputedRef<SharedGameContext>;
  viewer: ComputedRef<SharedViewer>;
  governors: ComputedRef<GovernorIdentity[]>;
  active: ComputedRef<ActiveGameContext | null>;
  governor: ComputedRef<GovernorIdentity | null>;
  alliance: ComputedRef<ActiveGameContext['alliance']>;
  kingdom: ComputedRef<ActiveGameContext['kingdom'] | null>;
  navigation: ComputedRef<GameNavigationItem[]>;
  hasAllianceCapability: (capability: string) => boolean;
  hasKingdomCapability: (capability: string) => boolean;
};
`;

const useGameContextTs = `import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import {
  EMPTY_GAME_CONTEXT,
  type GameContextView,
  type SharedGameContext,
  type SharedViewer,
} from '@/types/game-context';

export function useGameContext(): GameContextView {
  const page = usePage();
  const context = computed<SharedGameContext>(
    () =>
      ((page.props as Record<string, unknown>).gameContext as SharedGameContext | undefined) ??
      EMPTY_GAME_CONTEXT,
  );
  const viewer = computed<SharedViewer>(
    () => ((page.props as Record<string, unknown>).viewer as SharedViewer | undefined) ?? null,
  );
  const active = computed(() => context.value.active);
  const alliance = computed(() => active.value?.alliance ?? null);
  const kingdom = computed(() => active.value?.kingdom ?? null);

  return {
    context,
    viewer,
    governors: computed(() => context.value.governors),
    active,
    governor: computed(() => active.value?.governor ?? null),
    alliance,
    kingdom,
    navigation: computed(() => context.value.navigation),
    hasAllianceCapability: (capability: string): boolean =>
      alliance.value?.capabilities.includes(capability) ?? false,
    hasKingdomCapability: (capability: string): boolean =>
      kingdom.value?.capabilities.includes(capability) ?? false,
  };
}

export function useActiveGovernor() {
  return useGameContext().governor;
}

export function useAllianceContext() {
  return useGameContext().alliance;
}

export function useKingdomContext() {
  return useGameContext().kingdom;
}

export function useCapabilities() {
  const { hasAllianceCapability, hasKingdomCapability } = useGameContext();

  return { hasAllianceCapability, hasKingdomCapability };
}
`;

const contextIsolationTs = `import type { SharedGameContext } from '@/types/game-context';

type ContextDisposer = () => void;

const disposers = new Map<string, Set<ContextDisposer>>();
const frozenContexts = new Set<string>();

export function activeContextKey(context: SharedGameContext): string | null {
  return context.active?.authorityVersion ?? context.active?.fingerprint.key ?? null;
}

export function platformScopedStorageKey(key: string): string {
  return \`platform:\${key}\`;
}

export function governorScopedStorageKey(governorId: string, key: string): string {
  return \`governor:\${governorId}:\${key}\`;
}

export function contextScopedStorageKey(contextKey: string, key: string): string {
  return \`context:\${contextKey}:\${key}\`;
}

export function registerContextDisposer(contextKey: string, disposer: ContextDisposer): () => void {
  const bucket = disposers.get(contextKey) ?? new Set<ContextDisposer>();
  bucket.add(disposer);
  disposers.set(contextKey, bucket);

  return () => {
    bucket.delete(disposer);
    if (bucket.size === 0) disposers.delete(contextKey);
  };
}

export function createContextAbortController(contextKey: string): AbortController {
  const controller = new AbortController();
  const unregister = registerContextDisposer(contextKey, () => controller.abort());
  controller.signal.addEventListener('abort', unregister, { once: true });

  return controller;
}

export function beginContextTransition(contextKey: string | null): void {
  if (!contextKey || frozenContexts.has(contextKey)) return;

  frozenContexts.add(contextKey);
  dispatchContextEvent('kingshot:context-freeze', contextKey);
  runContextDisposers(contextKey);
}

export function completeContextTransition(contextKey: string | null): void {
  if (!contextKey) return;

  runContextDisposers(contextKey);
  frozenContexts.delete(contextKey);
  dispatchContextEvent('kingshot:context-invalidated', contextKey);
}

export function cancelContextTransition(contextKey: string | null): void {
  if (!contextKey) return;

  frozenContexts.delete(contextKey);
  dispatchContextEvent('kingshot:context-thaw', contextKey);
}

export function isContextFrozen(contextKey: string | null): boolean {
  return contextKey !== null && frozenContexts.has(contextKey);
}

function runContextDisposers(contextKey: string): void {
  const bucket = disposers.get(contextKey);
  if (!bucket) return;

  disposers.delete(contextKey);
  for (const dispose of bucket) dispose();
}

function dispatchContextEvent(name: string, contextKey: string): void {
  if (typeof window === 'undefined') return;

  window.dispatchEvent(new CustomEvent(name, { detail: { contextKey } }));
}
`;

const authorityContextTs = `import { activeContextKey } from '@/identity/context-isolation';
import { EMPTY_GAME_CONTEXT, type SharedGameContext } from '@/types/game-context';

export const AUTHORITY_CONTEXT_HEADER = 'X-Game-Context-Version';
export const AUTHORITY_CONTEXT_ERROR_HEADER = 'X-Game-Context-Error';
export const AUTHORITY_CONTEXT_STALE_EVENT = 'kingshot:authority-context-stale';

let currentVersion: string | null = null;
let currentContextKey: string | null = null;

export function updateAuthorityContextFromPageProps(props: Record<string, unknown>): void {
  const context = (props.gameContext as SharedGameContext | undefined) ?? EMPTY_GAME_CONTEXT;

  currentVersion = context.active?.authorityVersion ?? null;
  currentContextKey = activeContextKey(context);
}

export function authorityContextVersion(): string | null {
  return currentVersion;
}

export function authorityContextKey(): string | null {
  return currentContextKey;
}

export function authorityContextHeaders(
  headers: Record<string, string> = {},
): Record<string, string> {
  if (!currentVersion) return { ...headers };

  return {
    ...headers,
    [AUTHORITY_CONTEXT_HEADER]: currentVersion,
  };
}

export function isAuthorityContextStaleResponse(response: unknown): boolean {
  if (!response || typeof response !== 'object') return false;

  const candidate = response as { status?: unknown; headers?: unknown };
  if (candidate.status !== 409) return false;

  return (
    responseHeader(candidate.headers, AUTHORITY_CONTEXT_ERROR_HEADER)?.toLowerCase() === 'stale'
  );
}

export function dispatchAuthorityContextStale(): void {
  if (typeof window === 'undefined') return;

  window.dispatchEvent(new CustomEvent(AUTHORITY_CONTEXT_STALE_EVENT));
}

function responseHeader(headers: unknown, name: string): string | null {
  if (headers instanceof Headers) return headers.get(name);
  if (!headers || typeof headers !== 'object') return null;

  const source = headers as Record<string, unknown>;
  const direct = source[name] ?? source[name.toLowerCase()];
  if (typeof direct === 'string') return direct;
  if (Array.isArray(direct)) {
    const first = direct[0];
    return typeof first === 'string' ? first : null;
  }

  return null;
}
`;

const routeRegistryPhp = `<?php

declare(strict_types=1);

namespace App\\Contexts\\GameWorld\\Players\\Services;

/**
 * One presentation registry for Governor-context navigation and post-switch routing.
 *
 * This registry shapes UX only. Destination requests and every mutation remain
 * independently authorized by their owning context.
 */
final readonly class GameRouteRegistry
{
    /**
     * @var list<array{
     *     path:string,
     *     scope:'platform'|'governor'|'alliance',
     *     capability:?string,
     *     navigation:bool,
     *     key:?string,
     *     icon:?string,
     *     exact:bool
     * }>
     */
    private const DEFINITIONS = [
        ['path' => '/dashboard', 'scope' => 'platform', 'capability' => null, 'navigation' => true, 'key' => 'dashboard', 'icon' => 'dashboard', 'exact' => true],
        ['path' => '/profile', 'scope' => 'platform', 'capability' => null, 'navigation' => false, 'key' => null, 'icon' => null, 'exact' => true],
        ['path' => '/events', 'scope' => 'governor', 'capability' => null, 'navigation' => true, 'key' => 'events', 'icon' => 'events', 'exact' => false],
        ['path' => '/alliance', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'alliance', 'icon' => 'alliance', 'exact' => true],
        ['path' => '/alliance/recruitment', 'scope' => 'alliance', 'capability' => 'recruitment.manage', 'navigation' => true, 'key' => 'recruitment', 'icon' => 'recruitment', 'exact' => false],
        ['path' => '/alliance/kingdom-alliances', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'kingdom', 'icon' => 'kingdom', 'exact' => false],
        ['path' => '/alliance/roster', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'roster', 'icon' => 'roster', 'exact' => false],
        ['path' => '/alliance/contributions', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'contributions', 'icon' => 'contributions', 'exact' => false],
        ['path' => '/alliance/transfers', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'transfers', 'icon' => 'transfers', 'exact' => false],
        ['path' => '/alliance/content', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'content', 'icon' => 'content', 'exact' => false],
        ['path' => '/alliance/integrations', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'integrations', 'icon' => 'integrations', 'exact' => false],
    ];

    /**
     * @param  array{capabilities:list<string>}|null  $allianceContext
     * @return list<array{key:string,href:string,icon:string,exact:bool}>
     */
    public function navigation(bool $hasGovernor, ?array $allianceContext): array
    {
        $items = [];

        foreach (self::DEFINITIONS as $definition) {
            if (! $definition['navigation'] || ! $this->allowed($definition, $hasGovernor, $allianceContext)) {
                continue;
            }

            $key = $definition['key'];
            $icon = $definition['icon'];
            if (! is_string($key) || ! is_string($icon)) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'href' => $definition['path'],
                'icon' => $icon,
                'exact' => $definition['exact'],
            ];
        }

        return $items;
    }

    /**
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    public function resolveSwitchDestination(?string $requestedPath, ?array $allianceContext): string
    {
        $path = $this->normalize($requestedPath);
        if ($path === null) {
            return '/dashboard';
        }

        $definitions = self::DEFINITIONS;
        usort($definitions, static fn (array $left, array $right): int => strlen($right['path']) <=> strlen($left['path']));

        foreach ($definitions as $definition) {
            if (! $this->allowed($definition, true, $allianceContext)) {
                continue;
            }

            $base = $definition['path'];
            if ($path === $base || str_starts_with($path, $base.'/')) {
                return $path === $base ? $base : $base;
            }
        }

        if (str_starts_with($path, '/alliance/') && $allianceContext !== null) {
            return '/alliance';
        }

        return '/dashboard';
    }

    /**
     * @param  array{scope:string,capability:?string}  $definition
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    private function allowed(array $definition, bool $hasGovernor, ?array $allianceContext): bool
    {
        if ($definition['scope'] === 'platform') {
            return true;
        }

        if ($definition['scope'] === 'governor') {
            return $hasGovernor;
        }

        if (! $hasGovernor || $allianceContext === null) {
            return false;
        }

        $required = $definition['capability'];

        return $required === null || in_array($required, $allianceContext['capabilities'], true);
    }

    private function normalize(?string $requestedPath): ?string
    {
        if (
            $requestedPath === null
            || $requestedPath === ''
            || ! str_starts_with($requestedPath, '/')
            || str_starts_with($requestedPath, '//')
        ) {
            return null;
        }

        $path = parse_url($requestedPath, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = rtrim($path, '/');

        return $normalized === '' ? '/' : $normalized;
    }
}
`;

const handleInertiaPhp = `<?php

declare(strict_types=1);

namespace App\\Contexts\\GameWorld\\Players\\Http\\Middleware;

use App\\Contexts\\Accounts\\Identity\\Contracts\\AuthenticatedAccount;
use App\\Contexts\\Alliance\\Membership\\Queries\\PlayerIdentityContextQuery;
use App\\Contexts\\GameWorld\\Governance\\Queries\\KingdomAuthorityFactsQuery;
use App\\Contexts\\GameWorld\\Players\\Queries\\PlayerReferenceQuery;
use App\\Contexts\\GameWorld\\Players\\Services\\GameRouteRegistry;
use App\\Contexts\\GameWorld\\Players\\Services\\PlayerAuthorityContextVersion;
use App\\Contexts\\GameWorld\\Players\\Services\\PlayerContext;
use App\\Contexts\\GameWorld\\Players\\ValueObjects\\PlayerReference;
use Illuminate\\Http\\Request;
use Inertia\\Middleware;

/**
 * @phpstan-type AllianceIdentityContext array{
 *     membershipId:string,
 *     allianceId:string,
 *     allianceName:string,
 *     rank:string,
 *     roles:list<array{key:string,name:string}>,
 *     capabilities:list<string>
 * }
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly PlayerContext $playerContext,
        private readonly PlayerReferenceQuery $players,
        private readonly PlayerIdentityContextQuery $identityContext,
        private readonly KingdomAuthorityFactsQuery $kingdomAuthority,
        private readonly PlayerAuthorityContextVersion $authorityVersions,
        private readonly GameRouteRegistry $routes,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'applicationName' => config('app.name'),
            'viewer' => fn (): ?array => $this->viewerPayload($request),
            'gameContext' => fn (): array => $this->gameContextPayload($request),
        ];
    }

    /** @return array{id:int,name:string,email:string}|null */
    private function viewerPayload(Request $request): ?array
    {
        $user = $request->user();
        if (! $user instanceof AuthenticatedAccount) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }

    /** @return array<string, mixed> */
    private function gameContextPayload(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof AuthenticatedAccount) {
            return [
                'version' => 1,
                'governors' => [],
                'active' => null,
                'navigation' => [],
            ];
        }

        $players = $this->players->ownedByUser((int) $user->id);
        $allianceContexts = $this->identityContext->forPlayers(array_map(
            static fn (PlayerReference $player): string => $player->playerId,
            $players,
        ));
        $activePlayerId = $this->playerContext->playerOrNull()?->playerId;
        $activePlayer = null;

        foreach ($players as $player) {
            if ($player->playerId === $activePlayerId) {
                $activePlayer = $player;
                break;
            }
        }

        $governors = array_map(
            fn (PlayerReference $player): array => $this->governorPayload(
                $player,
                $allianceContexts[$player->playerId] ?? null,
            ),
            $players,
        );

        if (! $activePlayer instanceof PlayerReference) {
            return [
                'version' => 1,
                'governors' => $governors,
                'active' => null,
                'navigation' => $this->routes->navigation(false, null),
            ];
        }

        $activeAlliance = $allianceContexts[$activePlayer->playerId] ?? null;
        $kingdomPermissions = $this->kingdomAuthority
            ->findCurrent($activePlayer->playerId, $activePlayer->kingdomId)
            ?->permissionKeysObservedAtRead ?? [];
        sort($kingdomPermissions);

        $authorityVersion = $this->authorityVersions->issue(
            $activePlayer,
            $activeAlliance,
            $kingdomPermissions,
        );
        $routeAlliance = $activeAlliance === null ? null : ['capabilities' => $activeAlliance['capabilities']];

        return [
            'version' => 1,
            'governors' => $governors,
            'active' => [
                'governor' => $this->governorPayload($activePlayer, $activeAlliance),
                'kingdom' => [
                    'id' => $activePlayer->kingdomId,
                    'number' => $activePlayer->kingdomNumber,
                    'capabilities' => $kingdomPermissions,
                ],
                'alliance' => $activeAlliance === null ? null : [
                    'id' => $activeAlliance['allianceId'],
                    'membershipId' => $activeAlliance['membershipId'],
                    'name' => $activeAlliance['allianceName'],
                    'rank' => $activeAlliance['rank'],
                    'roles' => $activeAlliance['roles'],
                    'capabilities' => $activeAlliance['capabilities'],
                ],
                'fingerprint' => $this->fingerprint($activePlayer, $activeAlliance, $kingdomPermissions),
                'authorityVersion' => $authorityVersion,
            ],
            'navigation' => $this->routes->navigation(true, $routeAlliance),
        ];
    }

    /**
     * @param  AllianceIdentityContext|null  $membership
     * @return array<string, mixed>
     */
    private function governorPayload(PlayerReference $player, ?array $membership): array
    {
        return [
            'id' => $player->playerId,
            'name' => $player->currentName,
            'gamePlayerId' => $player->gamePlayerId,
            'kingdom' => [
                'id' => $player->kingdomId,
                'number' => $player->kingdomNumber,
            ],
            'alliance' => $membership === null ? null : [
                'id' => $membership['allianceId'],
                'membershipId' => $membership['membershipId'],
                'name' => $membership['allianceName'],
                'rank' => $membership['rank'],
                'roles' => $membership['roles'],
            ],
        ];
    }

    /**
     * @param  AllianceIdentityContext|null  $membership
     * @param  list<string>  $kingdomPermissions
     * @return array{version:1,key:string,playerId:string,kingdomId:string,kingdomNumber:?int,allianceId:?string,membershipId:?string}
     */
    private function fingerprint(PlayerReference $player, ?array $membership, array $kingdomPermissions): array
    {
        $roleKeys = array_map(
            static fn (array $role): string => $role['key'],
            $membership['roles'] ?? [],
        );
        sort($roleKeys);

        $scope = [
            'playerId' => $player->playerId,
            'kingdomId' => $player->kingdomId,
            'kingdomNumber' => $player->kingdomNumber,
            'allianceId' => $membership['allianceId'] ?? null,
            'membershipId' => $membership['membershipId'] ?? null,
            'rank' => $membership['rank'] ?? null,
            'roleKeys' => $roleKeys,
            'allianceCapabilities' => $membership['capabilities'] ?? [],
            'kingdomCapabilities' => $kingdomPermissions,
        ];

        return [
            'version' => 1,
            'key' => 'ctx:v1:'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)),
            'playerId' => $player->playerId,
            'kingdomId' => $player->kingdomId,
            'kingdomNumber' => $player->kingdomNumber,
            'allianceId' => $membership['allianceId'] ?? null,
            'membershipId' => $membership['membershipId'] ?? null,
        ];
    }
}
`;

const activateControllerPhp = `<?php

declare(strict_types=1);

namespace App\\Contexts\\GameWorld\\Players\\Http\\Controllers;

use App\\Contexts\\Alliance\\Membership\\Queries\\PlayerIdentityContextQuery;
use App\\Contexts\\GameWorld\\Players\\Actions\\ActivatePlayer;
use App\\Contexts\\GameWorld\\Players\\Services\\GameRouteRegistry;
use App\\Shared\\Infrastructure\\Http\\Controller;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;

final class ActivatePlayerController extends Controller
{
    public function __invoke(
        Request $request,
        string $player,
        ActivatePlayer $activatePlayer,
        PlayerIdentityContextQuery $identityContext,
        GameRouteRegistry $routes,
    ): RedirectResponse {
        $authId = Auth::id();
        abort_unless(is_numeric($authId), 401);

        $sessionKey = (string) config('game_world.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);

        $target = $activatePlayer->handle(
            (int) $authId,
            $player,
            is_string($previousPlayerId) ? $previousPlayerId : null,
        );

        $request->session()->put($sessionKey, $target->playerId);

        $targetAlliance = $identityContext->forPlayers([$target->playerId])[$target->playerId] ?? null;
        $routeAlliance = $targetAlliance === null ? null : [
            'capabilities' => $targetAlliance['capabilities'],
        ];
        $returnTo = $request->input('returnTo');
        $destination = $routes->resolveSwitchDestination(
            is_string($returnTo) ? $returnTo : null,
            $routeAlliance,
        );

        return redirect()->to($destination);
    }
}
`;

const identitySwitcherVue = `<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

import { useGameContext } from '@/composables/useGameContext';
import {
  activeContextKey,
  beginContextTransition,
  cancelContextTransition,
  completeContextTransition,
} from '@/identity/context-isolation';
import { useLocale } from '@/localization';
import type { GovernorIdentity } from '@/types/game-context';

withDefaults(
  defineProps<{
    compact?: boolean;
  }>(),
  { compact: false },
);

const { t } = useLocale();
const page = usePage();
const { context: gameContext, governor: activeGovernor, governors } = useGameContext();
const open = ref(false);
const switching = ref<string | null>(null);

const activeFingerprint = computed(() => activeContextKey(gameContext.value));
const currentPath = computed(() => {
  const [path] = page.url.split(/[?#]/);
  return path?.startsWith('/') ? path : '/dashboard';
});
const canSwitch = computed(() => governors.value.length > 1);
const switchingGovernor = computed(
  () => governors.value.find((governor) => governor.id === switching.value) ?? null,
);
const identityLabel = computed(() => {
  if (activeGovernor.value) return activeGovernor.value.name;
  if (governors.value.length > 0) return t('application.dashboard.selectPlayer');
  return t('common.noPlayers');
});
const switchStatus = computed(() =>
  switchingGovernor.value ? `${t('common.loading')}: ${switchingGovernor.value.name}` : '',
);

watch(activeFingerprint, (nextContextKey, previousContextKey) => {
  if (previousContextKey && previousContextKey !== nextContextKey) {
    completeContextTransition(previousContextKey);
  }
});

function roleLabel(governor: GovernorIdentity): string {
  if (!governor.alliance) return '';

  return [governor.alliance.rank.toUpperCase(), ...governor.alliance.roles.map((role) => role.name)]
    .filter(Boolean)
    .join(' · ');
}

function compactContextLabel(governor: GovernorIdentity): string {
  const pieces: string[] = [];
  if (governor.alliance?.name) pieces.push(governor.alliance.name);
  if (governor.kingdom.number) pieces.push(`K${governor.kingdom.number}`);
  if (governor.alliance?.rank) pieces.push(governor.alliance.rank.toUpperCase());
  return pieces.join(' · ');
}

function contextLabel(governor: GovernorIdentity): string {
  const pieces: string[] = [];
  if (governor.kingdom.number) pieces.push(`K${governor.kingdom.number}`);
  if (governor.alliance?.name) pieces.push(governor.alliance.name);

  const roles = roleLabel(governor);
  if (roles) pieces.push(roles);
  if (!governor.alliance) pieces.push(t('common.noPlayerAlliance'));

  return pieces.join(' · ');
}

function focusOption(position: 'active' | 'first' | 'last' | 'next' | 'previous'): void {
  const options = Array.from(
    document.querySelectorAll<HTMLButtonElement>('[data-governor-switch-option="true"]'),
  );
  if (options.length === 0) return;

  if (position === 'active') {
    const active = options.find((option) => option.dataset.governorId === activeGovernor.value?.id);
    (active ?? options[0])?.focus();
    return;
  }

  if (position === 'first') {
    options[0]?.focus();
    return;
  }

  if (position === 'last') {
    options.at(-1)?.focus();
    return;
  }

  const currentIndex = options.findIndex((option) => option === document.activeElement);
  const delta = position === 'next' ? 1 : -1;
  const nextIndex = currentIndex < 0 ? 0 : (currentIndex + delta + options.length) % options.length;
  options[nextIndex]?.focus();
}

function toggleOpen(): void {
  if (!canSwitch.value || switching.value) return;

  open.value = !open.value;
  if (open.value) void nextTick(() => focusOption('active'));
}

function close(): void {
  if (switching.value) return;
  open.value = false;
}

function activate(governorId: string): void {
  if (governorId === activeGovernor.value?.id || switching.value) {
    if (!switching.value) open.value = false;
    return;
  }

  const previousContextKey = activeFingerprint.value;
  switching.value = governorId;
  beginContextTransition(previousContextKey);

  router.post(
    `/players/${governorId}/activate`,
    { returnTo: currentPath.value },
    {
      preserveState: false,
      preserveScroll: false,
      onSuccess: () => completeContextTransition(previousContextKey),
      onError: () => {
        cancelContextTransition(previousContextKey);
        switching.value = null;
      },
      onFinish: () => {
        switching.value = null;
        open.value = false;
      },
    },
  );
}
</script>

<template>
  <div class="relative min-w-0">
    <p class="sr-only" aria-live="polite" aria-atomic="true">{{ switchStatus }}</p>

    <button
      type="button"
      class="group flex min-h-12 w-full min-w-0 items-center gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/20 px-3 py-2 text-start transition hover:border-[var(--ks-border-strong)] hover:bg-white/[0.025] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-teal-bright)]"
      :aria-expanded="canSwitch ? open : undefined"
      :aria-haspopup="canSwitch ? 'listbox' : undefined"
      :aria-busy="switching !== null"
      @click="toggleOpen"
      @keydown.down.prevent="canSwitch && (open ? focusOption('next') : toggleOpen())"
      @keydown.up.prevent="canSwitch && (open ? focusOption('previous') : toggleOpen())"
      @keydown.esc.prevent="close"
    >
      <span
        class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[radial-gradient(circle_at_38%_28%,#5a4c38,#17211f_68%)] font-[var(--ks-font-display)] font-bold text-[var(--ks-gold-bright)] shadow-inner"
        aria-hidden="true"
      >
        {{ activeGovernor?.name?.slice(0, 1).toUpperCase() ?? '—' }}
      </span>
      <span class="min-w-0 flex-1">
        <span
          class="block text-[0.62rem] font-extrabold tracking-[0.12em] text-[var(--ks-muted)] uppercase"
        >
          {{ t('common.currentPlayer') }}
        </span>
        <strong
          class="block truncate text-sm font-[var(--ks-font-display)] font-semibold text-[var(--ks-ivory)]"
        >
          {{ identityLabel }}
        </strong>
        <span
          v-if="activeGovernor && (!compact || compactContextLabel(activeGovernor))"
          class="mt-0.5 block truncate text-[0.68rem] text-[var(--ks-muted)]"
        >
          {{ compact ? compactContextLabel(activeGovernor) : contextLabel(activeGovernor) }}
        </span>
      </span>
      <svg
        v-if="canSwitch"
        class="h-4 w-4 shrink-0 text-[var(--ks-gold)] transition group-hover:text-[var(--ks-gold-bright)]"
        :class="open ? 'rotate-180' : ''"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="m6 8 4 4 4-4"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>

    <div
      v-if="open && canSwitch"
      class="absolute end-0 top-[calc(100%+.55rem)] z-[90] w-[min(27rem,calc(100vw-2rem))] overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border-strong)] bg-[rgba(7,13,13,.985)] shadow-[0_28px_80px_rgba(0,0,0,.65)] backdrop-blur-xl"
      role="listbox"
      :aria-label="t('application.dashboard.playerContextTitle')"
      :aria-busy="switching !== null"
      @keydown.down.prevent="focusOption('next')"
      @keydown.up.prevent="focusOption('previous')"
      @keydown.home.prevent="focusOption('first')"
      @keydown.end.prevent="focusOption('last')"
      @keydown.esc.prevent="close"
    >
      <div class="border-b border-[var(--ks-border)] px-4 py-3">
        <p class="ks-kicker">{{ t('application.dashboard.playerContextTitle') }}</p>
        <p class="mt-1 text-xs leading-5 text-[var(--ks-muted)]">
          {{ t('application.dashboard.playerContextIntro') }}
        </p>
      </div>

      <div class="max-h-[24rem] overflow-y-auto p-2">
        <button
          v-for="governor in governors"
          :key="governor.id"
          type="button"
          role="option"
          data-governor-switch-option="true"
          :data-governor-id="governor.id"
          :aria-selected="governor.id === activeGovernor?.id"
          :disabled="switching !== null"
          class="flex min-h-16 w-full items-center gap-3 rounded-[var(--ks-radius-md)] border px-3 py-3 text-start transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--ks-teal-bright)] disabled:cursor-wait disabled:opacity-65"
          :class="
            governor.id === activeGovernor?.id
              ? 'border-[rgba(32,178,163,.38)] bg-[rgba(20,153,141,.12)]'
              : 'border-transparent hover:border-[var(--ks-border)] hover:bg-white/[0.025]'
          "
          @click="activate(governor.id)"
        >
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-full border font-[var(--ks-font-display)] font-bold"
            :class="
              governor.id === activeGovernor?.id
                ? 'border-[var(--ks-teal-bright)] bg-[var(--ks-teal-soft)] text-[#aef6ea]'
                : 'border-[var(--ks-border)] bg-black/20 text-[var(--ks-gold-bright)]'
            "
            aria-hidden="true"
          >
            {{ governor.name.slice(0, 1).toUpperCase() }}
          </span>
          <span class="min-w-0 flex-1">
            <strong
              class="block truncate text-sm font-[var(--ks-font-display)] text-[var(--ks-ivory)]"
            >
              {{ governor.name }}
            </strong>
            <span class="mt-1 block truncate text-xs text-[var(--ks-muted)]">
              {{ contextLabel(governor) }}
            </span>
            <span
              v-if="governor.gamePlayerId"
              class="mt-1 block truncate text-[0.65rem] text-[var(--ks-muted)] opacity-75"
            >
              {{ governor.gamePlayerId }}
            </span>
          </span>
          <span
            v-if="governor.id === activeGovernor?.id"
            class="ks-status shrink-0"
            data-tone="success"
          >
            {{ t('common.active') }}
          </span>
          <span v-else-if="switching === governor.id" class="text-xs text-[var(--ks-gold)]">
            {{ t('common.loading') }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
`;

write('resources/js/types/game-context.ts', gameContextTs);
write('resources/js/composables/useGameContext.ts', useGameContextTs);
write('resources/js/identity/context-isolation.ts', contextIsolationTs);
write('resources/js/identity/authority-context.ts', authorityContextTs);
write('resources/js/components/navigation/IdentitySwitcher.vue', identitySwitcherVue);
write('app/Contexts/GameWorld/Players/Services/GameRouteRegistry.php', routeRegistryPhp);
write('app/Contexts/GameWorld/Players/Http/Middleware/HandleInertiaRequests.php', handleInertiaPhp);
write('app/Contexts/GameWorld/Players/Http/Controllers/ActivatePlayerController.php', activateControllerPhp);

let layout = read('resources/js/layouts/AppLayout.vue');
layout = layout.replace(/<script setup lang="ts">[\s\S]*?<\/script>/, `<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AllianceCrest from '@/components/game/AllianceCrest.vue';
import IdentitySwitcher from '@/components/navigation/IdentitySwitcher.vue';
import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import NavIcon from '@/components/navigation/NavIcon.vue';
import { useGameContext } from '@/composables/useGameContext';
import { useLocale } from '@/localization';
import type { GameNavigationItem } from '@/types/game-context';

const { t } = useLocale();
const page = usePage();
const mobileOpen = ref(false);
const { viewer, governor: activeGovernor, alliance: activeAlliance, navigation: rooms } = useGameContext();
const activeAllianceName = computed(() => activeAlliance.value?.name ?? null);
const currentPath = computed(() => page.url.split('?')[0]?.replace(/\\\/+$/, '') || '/');

function isActive(item: GameNavigationItem): boolean {
  const href = item.href.replace(/\\\/+$/, '') || '/';
  return item.exact
    ? currentPath.value === href
    : currentPath.value === href || currentPath.value.startsWith(\`${href}/\`);
}

function logout(): void {
  router.delete('/logout');
}
</script>`);

const desktopLoop = `<div class="space-y-1">
          <Link
            v-for="room in rooms"
            :key="room.href"
            :href="room.href"
            class="group relative flex min-h-11 items-center gap-3 overflow-hidden rounded-[var(--ks-radius-sm)] border px-3 py-2 text-[.9rem] font-[var(--ks-font-display)] transition"
            :class="
              isActive(room)
                ? 'border-[rgba(32,178,163,.38)] bg-[linear-gradient(90deg,rgba(10,121,113,.42),rgba(10,67,63,.12))] text-[#f6ecd7] shadow-[inset_3px_0_var(--ks-teal-bright)]'
                : 'border-transparent text-[var(--ks-text-muted)] hover:border-[var(--ks-border)] hover:bg-white/[0.025] hover:text-[var(--ks-text)]'
            "
          >
            <NavIcon
              :name="room.icon"
              class="h-5 w-5 shrink-0"
              :class="
                isActive(room)
                  ? 'text-[var(--ks-gold-bright)]'
                  : 'text-[var(--ks-gold)] opacity-80'
              "
            />
            <span class="min-w-0 flex-1 truncate">{{ t(\`navigation.\${room.key}\`) }}</span>
            <span v-if="isActive(room)" class="text-[var(--ks-teal-bright)]">›</span>
          </Link>
        </div>`;
const mobileLoop = `<div class="space-y-1">
            <Link
              v-for="room in rooms"
              :key="room.href"
              :href="room.href"
              class="flex min-h-12 items-center gap-3 rounded-[var(--ks-radius-sm)] border px-3 text-sm"
              :class="
                isActive(room)
                  ? 'border-[rgba(32,178,163,.38)] bg-[var(--ks-teal-soft)] text-[var(--ks-ivory)]'
                  : 'border-transparent text-[var(--ks-text-secondary)]'
              "
              @click="mobileOpen = false"
            >
              <NavIcon :name="room.icon" class="h-5 w-5 text-[var(--ks-gold)]" />
              <span class="flex-1">{{ t(\`navigation.\${room.key}\`) }}</span>
            </Link>
          </div>`;
const loops = [...layout.matchAll(/<div class="space-y-1">\s*<template v-for="room in rooms"[\s\S]*?<\/template>\s*<\/div>/g)];
if (loops.length !== 2) throw new Error(`Expected 2 navigation loops, found ${loops.length}`);
layout = layout.replace(loops[1][0], mobileLoop);
layout = layout.replace(loops[0][0], desktopLoop);
layout = layout.replaceAll('activePlayer', 'activeGovernor');
layout = layout.replaceAll('activeGovernor?.kingdomNumber', 'activeGovernor?.kingdom.number');
layout = layout.replaceAll('activeGovernor.kingdomNumber', 'activeGovernor.kingdom.number');
layout = layout.replaceAll('user.name', "viewer?.name ?? ''");
write('resources/js/layouts/AppLayout.vue', layout);

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(target) : [target];
  });
}

for (const absolute of walk(path.join(root, 'resources/js/pages'))) {
  if (!absolute.endsWith('.vue')) continue;
  let content = fs.readFileSync(absolute, 'utf8');
  if (!content.includes('<AppLayout')) continue;

  content = content.replace(/<AppLayout\b[\s\S]*?>/g, (tag) =>
    tag
      .replace(/\s+:user="user"/g, '')
      .replace(/\s+:has-player-alliance="[^"]*"/g, '')
      .replace(/\s+:player-alliance-name="[^"]*"/g, ''),
  );
  fs.writeFileSync(absolute, content);
}

remove('resources/js/types/player-context.ts');
remove('app/Contexts/GameWorld/Players/Services/PlayerSwitchRouteResolver.php');

const forbidden = [
  ['resources/js', 'SharedPlayerContext'],
  ['resources/js', 'playerContext'],
  ['resources/js', 'has-player-alliance'],
  ['resources/js', 'player-alliance-name'],
  ['app', 'PlayerSwitchRouteResolver'],
];
for (const [directory, needle] of forbidden) {
  for (const absolute of walk(path.join(root, directory))) {
    if (!fs.statSync(absolute).isFile()) continue;
    const content = fs.readFileSync(absolute, 'utf8');
    if (content.includes(needle)) {
      throw new Error(`Forbidden legacy contract ${needle} remains in ${path.relative(root, absolute)}`);
    }
  }
}
