<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Http\ActionReceipt;
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
            'webPushPublicKey' => config('services.webpush.public_key'),
            'actionReceipt' => fn (): mixed => $request->session()->get(ActionReceipt::SESSION_KEY),
            'playerContext' => fn (): array => $this->playerContextPayload($request),
        ];
    }

    /**
     * @return array{
     *     activePlayerId:?string,
     *     authorityContextVersion:?string,
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
            return [
                'activePlayerId' => null,
                'authorityContextVersion' => null,
                'players' => [],
            ];
        }

        $players = $this->players->ownedByUser((int) $user->id);
        $allianceContext = $this->identityContext->forPlayers(array_map(
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

        $authorityContextVersion = null;
        if ($activePlayer instanceof PlayerReference) {
            $activeAlliance = $allianceContext[$activePlayer->playerId] ?? null;
            $kingdomPermissions = $this->kingdomAuthority
                ->findCurrent($activePlayer->playerId, $activePlayer->kingdomId)
                ->permissionKeysObservedAtRead ?? [];

            $authorityContextVersion = $this->authorityVersions->issue(
                $activePlayer,
                $activeAlliance,
                $kingdomPermissions,
            );
        }

        return [
            'activePlayerId' => $activePlayerId,
            'authorityContextVersion' => $authorityContextVersion,
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
     * @param  AllianceIdentityContext|null  $membership
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
