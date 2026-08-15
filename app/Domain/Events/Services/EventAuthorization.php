<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\KingdomAuthorization;
use App\Domain\Events\Enums\EventScope;
use Illuminate\Auth\Access\AuthorizationException;

final class EventAuthorization
{
    public function __construct(
        private AllianceAuthorization $allianceAuthorization,
        private KingdomAuthorization $kingdomAuthorization,
        private PlayerEventAuthorization $playerAuthorization,
    ) {}

    public function allows(
        Player $actor,
        EventScope $scope,
        Alliance|Kingdom|Player $target,
        PermissionKey $permission,
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
        PermissionKey $permission,
    ): void {
        if (! $this->allows($actor, $scope, $target, $permission)) {
            throw new AuthorizationException;
        }
    }

    /** Shared scope/permission vocabulary used by read and mutation authorization. */
    public function supports(EventScope $scope, PermissionKey $permission): bool
    {
        return match ($scope) {
            EventScope::Player => in_array($permission, [
                PermissionKey::EventPlayerView,
                PermissionKey::EventPlayerCreate,
                PermissionKey::EventPlayerManage,
            ], true),
            EventScope::Alliance => in_array($permission, [
                PermissionKey::EventAllianceView,
                PermissionKey::EventAllianceCreate,
                PermissionKey::EventAllianceManage,
            ], true),
            EventScope::Kingdom => in_array($permission, [
                PermissionKey::EventKingdomView,
                PermissionKey::EventKingdomCreate,
                PermissionKey::EventKingdomManage,
            ], true),
        };
    }
}
