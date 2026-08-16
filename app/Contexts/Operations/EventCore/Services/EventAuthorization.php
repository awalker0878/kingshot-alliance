<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\ValueObjects\EventCreationMutationContext;
use App\Contexts\Operations\EventCore\ValueObjects\EventMutationContext;
use Illuminate\Auth\Access\AuthorizationException;

final class EventAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private PlayerEventAuthorization $playerAuthorization,
        private EventTargetResolver $targets,
    ) {}

    public function allows(
        Player $actor,
        EventScope $scope,
        Alliance|Kingdom|Player $target,
        OperationsPermission $permission,
    ): bool {
        if (! $this->supports($scope, $permission)) {
            return false;
        }

        return match ($scope) {
            EventScope::Player => $target instanceof Player
                && $this->playerAuthorization->allows($actor, $target, $permission),
            EventScope::Alliance => $target instanceof Alliance
                && $this->allianceAuthorization->allows($actor, $target, $permission),
            EventScope::Kingdom => $target instanceof Kingdom
                && $this->kingdomAuthorization->allows($actor, $target, $permission),
        };
    }

    public function authorize(
        Player $actor,
        EventScope $scope,
        Alliance|Kingdom|Player $target,
        OperationsPermission $permission,
    ): void {
        if (! $this->allows($actor, $scope, $target, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function authorizeManager(EventMutationContext $context): void
    {
        $permission = OperationsPermission::from((string) $context->typeScope->manage_permission_key);
        $this->authorize($context->actor, $context->event->scope, $context->target, $permission);
    }

    public function authorizeSelf(EventMutationContext $context, Player $participant): void
    {
        if ((string) $context->actor->id !== (string) $participant->id
            || ($context->event->scope === EventScope::Player
                && (! $context->target instanceof Player
                    || (string) $context->target->id !== (string) $participant->id))) {
            throw new AuthorizationException;
        }

        $permission = OperationsPermission::from((string) $context->typeScope->view_permission_key);
        $this->authorize($context->actor, $context->event->scope, $context->target, $permission);
    }

    public function authorizeCreation(EventCreationMutationContext $context, bool $manage = false): void
    {
        $scope = $this->targets->scopeFor($context->target);
        $permission = OperationsPermission::from((string) ($manage
            ? $context->typeScope->manage_permission_key
            : $context->typeScope->create_permission_key));

        $this->authorize($context->actor, $scope, $context->target, $permission);
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
}
