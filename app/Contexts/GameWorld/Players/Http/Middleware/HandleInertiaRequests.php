<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\GameRouteRegistry;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
        $routeAlliance = $activeAlliance === null ? null : [
            'capabilities' => $activeAlliance['capabilities'],
        ];

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
                'fingerprint' => $this->fingerprint(
                    $activePlayer,
                    $activeAlliance,
                    $kingdomPermissions,
                ),
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
