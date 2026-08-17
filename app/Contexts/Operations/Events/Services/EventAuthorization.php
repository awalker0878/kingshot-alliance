<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\ValueObjects\EventCreationMutationContext;
use App\Contexts\Operations\Events\ValueObjects\EventMutationContext;
use App\Contexts\Operations\Events\ValueObjects\EventScopeAuthorityFacts;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class EventAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private PlayerEventAuthorization $playerAuthorization,
        private EventTargetResolver $targets,
    ) {}

    /**
     * Read-time authorization. Protected writes must use the mutation-context
     * methods so current authority is reacquired inside the transaction.
     */
    public function allows(
        string $actorPlayerId,
        EventScope $scope,
        string $targetId,
        OperationsPermission $permission,
    ): bool {
        if (! $this->supports($scope, $permission)) {
            return false;
        }

        $target = $this->targets->resolve($scope, $targetId);

        return match ($scope) {
            EventScope::Player => $target->playerId !== null
                && $this->playerAuthorization->allows($actorPlayerId, $target->playerId, $permission),
            EventScope::Alliance => $target->allianceId !== null
                && $this->allianceAuthorization->allows($actorPlayerId, $target->allianceId, $permission),
            EventScope::Kingdom => $target->kingdomId !== null
                && $this->kingdomAuthorization->allows($actorPlayerId, $target->kingdomId, $permission),
        };
    }

    public function authorize(
        string $actorPlayerId,
        EventScope $scope,
        string $targetId,
        OperationsPermission $permission,
    ): void {
        if (! $this->allows($actorPlayerId, $scope, $targetId, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function authorizeManager(EventMutationContext $context): void
    {
        $permission = OperationsPermission::from((string) $context->typeScope->manage_permission_key);
        if (! $this->allowsCurrentFacts($context->actor->playerId, $context->target, $context->authority, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function authorizeSelf(EventMutationContext $context, string $participantPlayerId): void
    {
        if ($context->actor->playerId !== $participantPlayerId
            || ($context->target->scope === EventScope::Player
                && $context->target->playerId !== $participantPlayerId)) {
            throw new AuthorizationException;
        }

        $permission = OperationsPermission::from((string) $context->typeScope->view_permission_key);
        if (! $this->allowsCurrentFacts($context->actor->playerId, $context->target, $context->authority, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function authorizeCreation(EventCreationMutationContext $context, bool $manage = false): void
    {
        $permission = OperationsPermission::from((string) ($manage
            ? $context->typeScope->manage_permission_key
            : $context->typeScope->create_permission_key));

        if (! $this->allowsCurrentFacts($context->actor->playerId, $context->target, $context->authority, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function supports(EventScope $scope, OperationsPermission $permission): bool
    {
        return match ($scope) {
            EventScope::Player => in_array($permission, [
                OperationsPermission::EventPlayerView,
                OperationsPermission::EventPlayerCreate,
                OperationsPermission::EventPlayerManage,
            ], true),
            EventScope::Alliance => in_array($permission, [
                OperationsPermission::EventAllianceView,
                OperationsPermission::EventAllianceCreate,
                OperationsPermission::EventAllianceManage,
            ], true),
            EventScope::Kingdom => in_array($permission, [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
            ], true),
        };
    }

    private function allowsCurrentFacts(
        string $actorPlayerId,
        EventTargetReference $target,
        EventScopeAuthorityFacts $authority,
        OperationsPermission $permission,
    ): bool {
        if (! $this->supports($target->scope, $permission)) {
            return false;
        }

        return match ($target->scope) {
            EventScope::Alliance => $target->allianceId !== null
                && $authority->allianceFacts instanceof AllianceAuthorityFacts
                && $authority->allianceFacts->playerId === $actorPlayerId
                && $authority->allianceFacts->allianceId === $target->allianceId
                && $this->allianceAuthorization->allowsFacts($authority->allianceFacts, $permission),
            EventScope::Kingdom => $target->kingdomId !== null
                && $authority->kingdomFacts instanceof KingdomAuthorityFacts
                && $authority->kingdomFacts->playerId === $actorPlayerId
                && $authority->kingdomFacts->kingdomId === $target->kingdomId
                && $this->kingdomAuthorization->allowsFacts($authority->kingdomFacts, $permission),
            EventScope::Player => $target->playerId !== null
                && $this->playerAuthorization->allowsFacts(
                    $actorPlayerId,
                    $target->playerId,
                    $authority->playerManagerAllianceFacts,
                    $permission,
                ),
        };
    }
}
