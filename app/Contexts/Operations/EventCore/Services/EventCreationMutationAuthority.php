<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Governance\Services\KingdomMutationAuthority;
use App\Contexts\GameWorld\Governance\Services\PlayerMutationAuthority;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\EventCore\ValueObjects\EventCreationMutationContext;
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

        $permission = OperationsPermission::from((string) ($manage
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
        OperationsPermission $permission,
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
        OperationsPermission $permission,
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
        OperationsPermission $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Player
            || (string) $target->id !== (string) $actor->id
            || ! in_array($permission, [OperationsPermission::EventPlayerCreate, OperationsPermission::EventPlayerManage], true)) {
            throw new AuthorizationException;
        }

        $context = $this->playerAuthority->require($actor);

        return new EventCreationMutationContext($configuration, $context->actor, $context->actor);
    }
}
