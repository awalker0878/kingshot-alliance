<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Governance\Services\PlayerWriteState;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\EventCore\ValueObjects\EventCreationMutationContext;
use App\Contexts\Operations\EventCore\ValueObjects\EventMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EventAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private PlayerEventAuthorization $playerAuthorization,
        private EventTargetResolver $targets,
        private PlayerWriteState $playerWriteState,
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

    public function requireManager(Player $actor, Event $event): EventMutationContext
    {
        return $this->requireManagerWithEventLock($actor, $event, false);
    }

    public function requireManagerExclusive(Player $actor, Event $event): EventMutationContext
    {
        return $this->requireManagerWithEventLock($actor, $event, true);
    }

    public function requireSelf(Player $actor, Event $event, Player $participant): EventMutationContext
    {
        $this->assertTransaction();
        $route = $this->freshRoute($event);
        $typeScope = $this->lockTypeScope($route);

        if ((string) $actor->id !== (string) $participant->id
            || ($route->scope === EventScope::Player
                && (string) $route->player_id !== (string) $participant->id)) {
            throw new AuthorizationException;
        }

        $permission = OperationsPermission::from((string) $typeScope->view_permission_key);
        [$currentActor, $target] = $this->authorizeScope($actor, $route, $permission);
        $currentEvent = $this->lockAndRevalidateEvent($route, false);

        if ($currentEvent->scope === EventScope::Alliance) {
            if (! $target instanceof Alliance
                || (string) $currentActor->current_kingdom_id !== (string) $target->kingdom_id
                || ! AllianceRosterEntry::query()
                    ->where('alliance_id', $target->id)
                    ->where('player_id', $currentActor->id)
                    ->where('state', RosterState::Active->value)
                    ->sharedLock()
                    ->exists()) {
                throw new AuthorizationException;
            }
        } elseif ($currentEvent->scope === EventScope::Kingdom) {
            if (! $target instanceof Kingdom
                || (string) $currentActor->current_kingdom_id !== (string) $target->id) {
                throw new AuthorizationException;
            }
        }

        return new EventMutationContext($currentEvent, $typeScope, $currentActor, $target);
    }

    public function requireCreate(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
    ): EventCreationMutationContext {
        return $this->requireCreation($actor, $configuration, $target, false);
    }

    public function requireManage(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
    ): EventCreationMutationContext {
        return $this->requireCreation($actor, $configuration, $target, true);
    }

    private function requireCreation(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        bool $manage,
    ): EventCreationMutationContext {
        $this->assertTransaction();
        $scope = $this->targets->scopeFor($target);
        $currentConfiguration = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->where('scope', $scope->value)
            ->sharedLock()
            ->firstOrFail();

        $permission = OperationsPermission::from((string) ($manage
            ? $currentConfiguration->manage_permission_key
            : $currentConfiguration->create_permission_key));

        if (! $this->supports($scope, $permission)) {
            throw new AuthorizationException;
        }

        return match ($scope) {
            EventScope::Alliance => $this->creationForAlliance($actor, $currentConfiguration, $target, $permission),
            EventScope::Kingdom => $this->creationForKingdom($actor, $currentConfiguration, $target, $permission),
            EventScope::Player => $this->creationForPlayer($actor, $currentConfiguration, $target, $permission),
        };
    }

    private function creationForAlliance(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        OperationsPermission $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Alliance) {
            throw new AuthorizationException;
        }

        $alliance = Alliance::query()->whereKey($target->id)->firstOrFail();
        $context = $this->allianceAuthorization->require($actor, $alliance, $permission);

        return new EventCreationMutationContext($configuration, $context->actor, $context->alliance);
    }

    private function creationForKingdom(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        OperationsPermission $permission,
    ): EventCreationMutationContext {
        if (! $target instanceof Kingdom) {
            throw new AuthorizationException;
        }

        $kingdom = Kingdom::query()->whereKey($target->id)->firstOrFail();
        $context = $this->kingdomAuthorization->require($actor, $kingdom, $permission);

        return new EventCreationMutationContext($configuration, $context->actor, $context->kingdom);
    }

    private function creationForPlayer(
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

        $context = $this->playerWriteState->require($actor);

        return new EventCreationMutationContext($configuration, $context->actor, $context->actor);
    }

    private function requireManagerWithEventLock(Player $actor, Event $event, bool $exclusiveEvent): EventMutationContext
    {
        $this->assertTransaction();
        $route = $this->freshRoute($event);
        $typeScope = $this->lockTypeScope($route);
        $permission = OperationsPermission::from((string) $typeScope->manage_permission_key);
        [$currentActor, $target] = $this->authorizeScope($actor, $route, $permission);
        $currentEvent = $this->lockAndRevalidateEvent($route, $exclusiveEvent);

        return new EventMutationContext($currentEvent, $typeScope, $currentActor, $target);
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event transactional authorization must run inside a database transaction.');
        }
    }

    private function freshRoute(Event $event): Event
    {
        return Event::query()
            ->select(['id', 'event_type_scope_id', 'scope', 'alliance_id', 'kingdom_id', 'player_id'])
            ->whereKey($event->id)
            ->firstOrFail();
    }

    private function lockTypeScope(Event $route): EventTypeScope
    {
        return EventTypeScope::query()
            ->whereKey($route->event_type_scope_id)
            ->where('scope', $route->scope->value)
            ->sharedLock()
            ->firstOrFail();
    }

    /** @return array{Player,Alliance|Kingdom|Player} */
    private function authorizeScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        if (! $this->supports($event->scope, $permission)) {
            throw new AuthorizationException;
        }

        return match ($event->scope) {
            EventScope::Alliance => $this->authorizeAllianceScope($actor, $event, $permission),
            EventScope::Kingdom => $this->authorizeKingdomScope($actor, $event, $permission),
            EventScope::Player => $this->authorizePlayerScope($actor, $event, $permission),
        };
    }

    /** @return array{Player,Alliance} */
    private function authorizeAllianceScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        $alliance = Alliance::query()->whereKey($event->alliance_id)->firstOrFail();
        $context = $this->allianceAuthorization->require($actor, $alliance, $permission);

        return [$context->actor, $context->alliance];
    }

    /** @return array{Player,Kingdom} */
    private function authorizeKingdomScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        $kingdom = Kingdom::query()->whereKey($event->kingdom_id)->firstOrFail();
        $context = $this->kingdomAuthorization->require($actor, $kingdom, $permission);

        return [$context->actor, $context->kingdom];
    }

    /** @return array{Player,Player} */
    private function authorizePlayerScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        $target = Player::query()->whereKey($event->player_id)->firstOrFail();

        if ((string) $actor->id === (string) $target->id) {
            $context = $this->playerWriteState->require($actor);
            return [$context->actor, $context->actor];
        }

        if ($permission === OperationsPermission::EventPlayerCreate) {
            throw new AuthorizationException;
        }

        $candidateAllianceIds = AllianceRosterEntry::query()
            ->where('player_id', $target->id)
            ->where('state', RosterState::Active->value)
            ->orderBy('alliance_id')
            ->pluck('alliance_id')
            ->unique()
            ->values();

        foreach ($candidateAllianceIds as $allianceId) {
            $alliance = Alliance::query()->whereKey($allianceId)->first();
            if (! $alliance instanceof Alliance) {
                continue;
            }

            try {
                $managerContext = $this->allianceAuthorization->require($actor, $alliance, $permission);
            } catch (AuthorizationException) {
                continue;
            }

            $currentTarget = Player::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $eligible = (string) $currentTarget->current_kingdom_id === (string) $managerContext->alliance->kingdom_id
                && AllianceRosterEntry::query()
                    ->where('alliance_id', $managerContext->alliance->id)
                    ->where('player_id', $currentTarget->id)
                    ->where('state', RosterState::Active->value)
                    ->sharedLock()
                    ->exists();

            if ($eligible) {
                return [$managerContext->actor, $currentTarget];
            }
        }

        throw new AuthorizationException;
    }

    private function lockAndRevalidateEvent(Event $route, bool $exclusive): Event
    {
        $query = Event::query()->whereKey($route->id);
        $locked = $exclusive
            ? $query->lockForUpdate()->firstOrFail()
            : $query->sharedLock()->firstOrFail();

        if ($locked->scope !== $route->scope
            || (string) $locked->event_type_scope_id !== (string) $route->event_type_scope_id
            || (string) ($locked->alliance_id ?? '') !== (string) ($route->alliance_id ?? '')
            || (string) ($locked->kingdom_id ?? '') !== (string) ($route->kingdom_id ?? '')
            || (string) ($locked->player_id ?? '') !== (string) ($route->player_id ?? '')) {
            throw new AuthorizationException('The Event target changed while the write was being prepared.');
        }

        return $locked;
    }
}
