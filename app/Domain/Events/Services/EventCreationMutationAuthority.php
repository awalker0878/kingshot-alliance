<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Authorization\Services\KingdomMutationAuthority;
use App\Domain\Authorization\Services\PlayerMutationAuthority;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\ValueObjects\EventCreationMutationContext;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Event-owned authority boundary for Event/template creation where no Event row exists yet. */
final readonly class EventCreationMutationAuthority
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventTargetResolver $targets,
        private AllianceMutationAuthority $allianceAuthority,
        private KingdomMutationAuthority $kingdomAuthority,
        private PlayerMutationAuthority $playerAuthority,
    ) {}

    public function requireCreate(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
    ): EventCreationMutationContext {
        return $this->require($actor, $configuration, $target, false);
    }

    public function requireManage(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
    ): EventCreationMutationContext {
        return $this->require($actor, $configuration, $target, true);
    }

    private function require(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        bool $manage,
    ): EventCreationMutationContext {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event creation mutation authority must be acquired inside a database transaction.');
        }

        $scope = $this->targets->scopeFor($target);
        $currentConfiguration = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->where('scope', $scope->value)
            ->sharedLock()
            ->firstOrFail();

        $permission = PermissionKey::from((string) ($manage
            ? $currentConfiguration->manage_permission_key
            : $currentConfiguration->create_permission_key));
        if (! $this->authorization->supports($scope, $permission)) {
            throw new AuthorizationException;
        }

        return match ($scope) {
            EventScope::Alliance => $this->forAlliance($actor, $currentConfiguration, $target, $permission),
            EventScope::Kingdom => $this->forKingdom($actor, $currentConfiguration, $target, $permission),
            EventScope::Player => $this->forPlayer($actor, $currentConfiguration, $target, $permission),
        };
    }

    private function forAlliance(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        PermissionKey $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Alliance) {
            throw new AuthorizationException;
        }

        $alliance = Alliance::query()->whereKey($target->id)->firstOrFail();
        $context = $this->allianceAuthority->require($actor, $alliance, $permission);

        return new EventCreationMutationContext($configuration, $context->actor, $context->alliance);
    }

    private function forKingdom(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        PermissionKey $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Kingdom) {
            throw new AuthorizationException;
        }

        $kingdom = Kingdom::query()->whereKey($target->id)->firstOrFail();
        $context = $this->kingdomAuthority->require($actor, $kingdom, $permission);

        return new EventCreationMutationContext($configuration, $context->actor, $context->kingdom);
    }

    private function forPlayer(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        PermissionKey $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Player
            || (string) $target->id !== (string) $actor->id
            || ! in_array($permission, [PermissionKey::EventPlayerCreate, PermissionKey::EventPlayerManage], true)) {
            throw new AuthorizationException;
        }

        $context = $this->playerAuthority->require($actor);

        return new EventCreationMutationContext($configuration, $context->actor, $context->actor);
    }
}
