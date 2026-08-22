<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceOperationsAuthorization
{
    public function __construct(private AllianceAuthorityFactsQuery $authorityFacts) {}

    public function allows(string $actorPlayerId, string $allianceId, OperationsPermission $permission): bool
    {
        $facts = $this->authorityFacts->findCurrent($actorPlayerId, $allianceId);

        return $facts instanceof AllianceAuthorityFacts && $this->allowsFacts($facts, $permission);
    }

    public function allowsFacts(AllianceAuthorityFacts $facts, OperationsPermission $permission): bool
    {
        return $this->allowsRoleState($facts->rankObservedAtRead, $facts->roleKeysObservedAtRead, $permission);
    }

    public function authorizeFacts(AllianceAuthorityFacts $facts, OperationsPermission $permission): void
    {
        if (! $this->allowsFacts($facts, $permission)) {
            throw new AuthorizationException;
        }
    }

    /** @param list<string> $roleKeys */
    private function allowsRoleState(AllianceRank $rank, array $roleKeys, OperationsPermission $permission): bool
    {
        $officer = in_array($rank, [AllianceRank::R4, AllianceRank::R5], true);
        $coordinator = in_array('event_coordinator', $roleKeys, true);

        return match ($permission) {
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventAllianceView,
            OperationsPermission::TerritoryAllianceView => true,
            OperationsPermission::EventPlayerManage => $officer,
            OperationsPermission::EventAllianceCreate,
            OperationsPermission::EventAllianceManage,
            OperationsPermission::TerritoryAllianceManage => $officer || $coordinator,
            default => false,
        };
    }
}
