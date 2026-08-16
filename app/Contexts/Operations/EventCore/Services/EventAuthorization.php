<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use Illuminate\Auth\Access\AuthorizationException;

final class EventAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomAuthorization $kingdomAuthorization,
        private PlayerEventAuthorization $playerAuthorization,
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

    /** Shared scope/permission vocabulary used by read and mutation authorization. */
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
