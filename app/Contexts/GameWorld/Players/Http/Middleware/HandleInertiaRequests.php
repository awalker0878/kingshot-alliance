<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\ActiveGovernorAuthorityContextResolver;
use App\Contexts\GameWorld\Players\Services\GameRouteRegistry;
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
        private readonly ActiveGovernorAuthorityContextResolver $authorityContext,
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
        $governors = array_map(
            fn (PlayerReference $player): array => $this->governorPayload(
                $player,
                $allianceContexts[$player->playerId] ?? null,
            ),
            $players,
        );
        $activePlayerId = $this->playerContext->playerOrNull()?->playerId;

        if ($activePlayerId === null) {
            return [
                'version' => 1,
                'governors' => $governors,
                'active' => null,
                'navigation' => $this->routes->navigation(false, null),
            ];
        }

        // Resolve the active authority snapshot through the same canonical service
        // used by stale-write protection. This avoids two subtly different notions
        // of the Governor context reaching the browser and mutation precondition.
        $active = $this->authorityContext->resolveOwned((int) $user->id, $activePlayerId);
        if ($active === null) {
            return [
                'version' => 1,
                'governors' => $governors,
                'active' => null,
                'navigation' => $this->routes->navigation(false, null),
            ];
        }

        return [
            'version' => 1,
            'governors' => $governors,
            'active' => $active->activePayload(),
            'navigation' => $this->routes->navigation(true, $active->routeAllianceContext()),
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
}
