<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

/**
 * Issues an opaque, deterministic staleness precondition for the authority
 * context a Platform User actually reviewed before starting a game mutation.
 *
 * The value is not authorization. Protected writes must still resolve and lock
 * their current authority in the owning context before mutating state.
 */
final class PlayerAuthorityContextVersion
{
    /**
     * @param  array{
     *     membershipId:string,
     *     allianceId:string,
     *     allianceName:string,
     *     rank:string,
     *     roles:list<array{key:string,name:string}>,
     *     capabilities:list<string>
     * }|null  $allianceContext
     * @param  list<string>  $kingdomPermissions
     */
    public function issue(
        PlayerReference $player,
        ?array $allianceContext,
        array $kingdomPermissions,
    ): string {
        $roleKeys = array_map(
            static fn (array $role): string => $role['key'],
            $allianceContext['roles'] ?? [],
        );
        sort($roleKeys);

        $allianceCapabilities = array_values(array_unique($allianceContext['capabilities'] ?? []));
        sort($allianceCapabilities);

        $kingdomPermissions = array_values(array_unique($kingdomPermissions));
        sort($kingdomPermissions);

        $scope = [
            'playerId' => $player->playerId,
            'userId' => $player->userId,
            'kingdomId' => $player->kingdomId,
            'allianceId' => $allianceContext['allianceId'] ?? null,
            'membershipId' => $allianceContext['membershipId'] ?? null,
            'rank' => $allianceContext['rank'] ?? null,
            'roleKeys' => $roleKeys,
            'allianceCapabilities' => $allianceCapabilities,
            'kingdomPermissions' => $kingdomPermissions,
        ];

        return 'authctx:v1:'.hash(
            'sha256',
            json_encode($scope, JSON_THROW_ON_ERROR),
        );
    }
}
