<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\ActiveGovernorAuthorityContext;

final readonly class ActiveGovernorAuthorityContextResolver
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private PlayerIdentityContextQuery $allianceContext,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
        private PlayerAuthorityContextVersion $versions,
    ) {}

    /**
     * Re-resolves the selected Governor from persistence before issuing a snapshot.
     * The caller must provide the authenticated Platform User id; ownership is part
     * of resolution rather than trusted from the request-scoped selection.
     */
    public function resolveOwned(int $userId, string $playerId): ?ActiveGovernorAuthorityContext
    {
        $governor = $this->players->findOwnedByUser($userId, $playerId);
        if ($governor === null) {
            return null;
        }

        $alliance = $this->allianceContext->forPlayers([$governor->playerId])[$governor->playerId] ?? null;
        $roles = $alliance['roles'] ?? [];
        usort($roles, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);

        $allianceCapabilities = array_values(array_unique($alliance['capabilities'] ?? []));
        sort($allianceCapabilities);

        $kingdomCapabilities = $this->normalizedKingdomCapabilities(
            $this->kingdomAuthority->findCurrent($governor->playerId, $governor->kingdomId),
        );

        $normalizedAlliance = $alliance === null ? null : [
            'membershipId' => $alliance['membershipId'],
            'allianceId' => $alliance['allianceId'],
            'allianceName' => $alliance['allianceName'],
            'rank' => $alliance['rank'],
            'roles' => $roles,
            'capabilities' => $allianceCapabilities,
        ];

        return new ActiveGovernorAuthorityContext(
            governor: $governor,
            allianceId: $normalizedAlliance['allianceId'] ?? null,
            membershipId: $normalizedAlliance['membershipId'] ?? null,
            allianceName: $normalizedAlliance['allianceName'] ?? null,
            allianceRank: $normalizedAlliance['rank'] ?? null,
            allianceRoles: $roles,
            allianceCapabilities: $allianceCapabilities,
            kingdomCapabilities: $kingdomCapabilities,
            authorityVersion: $this->versions->issue(
                $governor,
                $normalizedAlliance,
                $kingdomCapabilities,
            ),
        );
    }

    /** @return list<string> */
    private function normalizedKingdomCapabilities(?KingdomAuthorityFacts $authority): array
    {
        if ($authority === null) {
            return [];
        }

        $capabilities = array_values(array_unique($authority->permissionKeysObservedAtRead));
        sort($capabilities);

        return $capabilities;
    }
}
