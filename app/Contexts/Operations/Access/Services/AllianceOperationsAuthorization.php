<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;

final class AllianceOperationsAuthorization
{
    public function __construct(private AllianceAuthorityFactsQuery $authorityFacts) {}

    /**
     * Read-time authorization only. Protected writes must perform their
     * authorization again inside their transaction against current state.
     */
    public function allows(string $actorPlayerId, string $allianceId, OperationsPermission $permission): bool
    {
        $facts = $this->authorityFacts->findCurrent($actorPlayerId, $allianceId);

        return $facts instanceof AllianceAuthorityFacts
            && $this->allowsFacts($facts, $permission);
    }

    public function authorizeContext(AllianceMutationContext $context, OperationsPermission $permission): void
    {
        $isOfficer = in_array($context->membership->rank, [AllianceRank::R4, AllianceRank::R5], true);
        $isEventCoordinator = $context->membership->roles()
            ->where('roles.alliance_id', $context->alliance->id)
            ->where('roles.key', DefaultAllianceRole::EventCoordinator->value)
            ->exists();

        if (! $this->allowsRoleState($isOfficer, $isEventCoordinator, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function allowsFacts(AllianceAuthorityFacts $facts, OperationsPermission $permission): bool
    {
        return $this->allowsRoleState(
            isOfficer: in_array($facts->rankObservedAtRead, [AllianceRank::R4, AllianceRank::R5], true),
            isEventCoordinator: $facts->hasRoleObservedAtRead(DefaultAllianceRole::EventCoordinator->value),
            permission: $permission,
        );
    }

    private function allowsRoleState(
        bool $isOfficer,
        bool $isEventCoordinator,
        OperationsPermission $permission,
    ): bool {
        return match ($permission) {
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventAllianceView => true,
            OperationsPermission::EventPlayerManage => $isOfficer,
            OperationsPermission::EventAllianceCreate,
            OperationsPermission::EventAllianceManage => $isOfficer || $isEventCoordinator,
            default => false,
        };
    }
}
