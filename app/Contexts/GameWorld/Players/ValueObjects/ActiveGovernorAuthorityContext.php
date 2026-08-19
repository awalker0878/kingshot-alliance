<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\ValueObjects;

/**
 * Immutable, scalar/reference-only snapshot of the current game authority context.
 *
 * This object is presentation/staleness context, not authorization. Owning domains
 * must still resolve and lock their own authority inside write transactions.
 *
 * @phpstan-type AllianceRole array{key:string,name:string}
 */
final readonly class ActiveGovernorAuthorityContext
{
    /**
     * @param  list<AllianceRole>  $allianceRoles
     * @param  list<string>  $allianceCapabilities
     * @param  list<string>  $kingdomCapabilities
     */
    public function __construct(
        public PlayerReference $governor,
        public ?string $allianceId,
        public ?string $membershipId,
        public ?string $allianceName,
        public ?string $allianceRank,
        public array $allianceRoles,
        public array $allianceCapabilities,
        public array $kingdomCapabilities,
        public string $authorityVersion,
    ) {}

    /** @return array<string, mixed> */
    public function activePayload(): array
    {
        return [
            'governor' => $this->governorPayload(),
            'kingdom' => [
                'id' => $this->governor->kingdomId,
                'number' => $this->governor->kingdomNumber,
                'capabilities' => $this->kingdomCapabilities,
            ],
            'alliance' => $this->allianceId === null ? null : [
                'id' => $this->allianceId,
                'membershipId' => $this->membershipId,
                'name' => $this->allianceName,
                'rank' => $this->allianceRank,
                'roles' => $this->allianceRoles,
                'capabilities' => $this->allianceCapabilities,
            ],
            'fingerprint' => $this->fingerprint(),
            'authorityVersion' => $this->authorityVersion,
        ];
    }

    /** @return array<string, mixed> */
    public function governorPayload(): array
    {
        return [
            'id' => $this->governor->playerId,
            'name' => $this->governor->currentName,
            'gamePlayerId' => $this->governor->gamePlayerId,
            'kingdom' => [
                'id' => $this->governor->kingdomId,
                'number' => $this->governor->kingdomNumber,
            ],
            'alliance' => $this->allianceId === null ? null : [
                'id' => $this->allianceId,
                'membershipId' => $this->membershipId,
                'name' => $this->allianceName,
                'rank' => $this->allianceRank,
                'roles' => $this->allianceRoles,
            ],
        ];
    }

    /** @return array{capabilities:list<string>}|null */
    public function routeAllianceContext(): ?array
    {
        if ($this->allianceId === null) {
            return null;
        }

        return ['capabilities' => $this->allianceCapabilities];
    }

    /**
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
    public function fingerprint(): array
    {
        $scope = [
            'playerId' => $this->governor->playerId,
            'kingdomId' => $this->governor->kingdomId,
            'kingdomNumber' => $this->governor->kingdomNumber,
            'allianceId' => $this->allianceId,
            'membershipId' => $this->membershipId,
            'rank' => $this->allianceRank,
            'roleKeys' => array_map(
                static fn (array $role): string => $role['key'],
                $this->allianceRoles,
            ),
            'allianceCapabilities' => $this->allianceCapabilities,
            'kingdomCapabilities' => $this->kingdomCapabilities,
        ];

        return [
            'version' => 1,
            'key' => 'ctx:v1:'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)),
            'playerId' => $this->governor->playerId,
            'kingdomId' => $this->governor->kingdomId,
            'kingdomNumber' => $this->governor->kingdomNumber,
            'allianceId' => $this->allianceId,
            'membershipId' => $this->membershipId,
        ];
    }
}
