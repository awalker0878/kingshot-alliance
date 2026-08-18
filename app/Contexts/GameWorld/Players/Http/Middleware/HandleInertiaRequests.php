<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly PlayerContext $playerContext,
        private readonly PlayerReferenceQuery $players,
        private readonly PlayerIdentityContextQuery $identityContext,
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
            'playerContext' => fn (): array => $this->playerContextPayload($request),
        ];
    }

    /**
     * @return array{
     *     activePlayerId:?string,
     *     players:list<array{
     *         id:string,
     *         name:string,
     *         gamePlayerId:?string,
     *         kingdomNumber:?int,
     *         alliance:?array{
     *             id:string,
     *             membershipId:string,
     *             name:string,
     *             rank:string,
     *             roles:list<array{key:string,name:string}>,
     *             capabilities:list<string>
     *         },
     *         contextFingerprint:array{
     *             version:1,
     *             key:string,
     *             playerId:string,
     *             kingdomId:string,
     *             kingdomNumber:?int,
     *             allianceId:?string,
     *             membershipId:?string
     *         }
     *     }>
     * }
     */
    private function playerContextPayload(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof AuthenticatedAccount) {
            return ['activePlayerId' => null, 'players' => []];
        }

        $players = $this->players->ownedByUser((int) $user->id);
        $allianceContext = $this->identityContext->forPlayers(array_map(
            static fn (PlayerReference $player): string => $player->playerId,
            $players,
        ));

        return [
            'activePlayerId' => $this->playerContext->playerOrNull()?->playerId,
            'players' => array_map(function (PlayerReference $player) use ($allianceContext): array {
                $membership = $allianceContext[$player->playerId] ?? null;

                return [
                    'id' => $player->playerId,
                    'name' => $player->currentName,
                    'gamePlayerId' => $player->gamePlayerId,
                    'kingdomNumber' => $player->kingdomNumber,
                    'alliance' => $membership === null ? null : [
                        'id' => $membership['allianceId'],
                        'membershipId' => $membership['membershipId'],
                        'name' => $membership['allianceName'],
                        'rank' => $membership['rank'],
                        'roles' => $membership['roles'],
                        'capabilities' => $membership['capabilities'],
                    ],
                    'contextFingerprint' => $this->fingerprint($player, $membership),
                ];
            }, $players),
        ];
    }

    /**
     * @param  array{
     *     membershipId:string,
     *     allianceId:string,
     *     allianceName:string,
     *     rank:string,
     *     roles:list<array{key:string,name:string}>,
     *     capabilities:list<string>
     * }|null  $membership
     * @return array{
     *     version:1,
     *     key:string,
     *     playerId:string,
     *     kingdomId:string,
     *     kingdomNumber:?int,
     *     allianceId:?string,
     *     membershipId:?string
     * }
     */
    private function fingerprint(PlayerReference $player, ?array $membership): array
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
            'capabilities' => $membership['capabilities'] ?? [],
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
