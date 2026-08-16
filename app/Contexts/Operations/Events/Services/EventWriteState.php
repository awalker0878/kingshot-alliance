<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Governance\Services\PlayerWriteState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\ValueObjects\EventCreationMutationContext;
use App\Contexts\Operations\Events\ValueObjects\EventMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Policy-free transaction-time state acquisition for Event write paths.
 *
 * This service stabilizes the Event route, configured scope, active actor, and
 * target records. Permission vocabulary remains entirely in EventAuthorization
 * and the scope-specific authorization services.
 */
final class EventWriteState
{
    public function __construct(
        private EventTargetResolver $targets,
        private AllianceWriteState $allianceWriteState,
        private KingdomWriteState $kingdomWriteState,
        private PlayerWriteState $playerWriteState,
    ) {}

    public function lockEventScope(
        Player $actor,
        Event $event,
        bool $exclusiveEvent = false,
    ): EventMutationContext {
        $this->assertTransaction();

        $route = Event::query()
            ->select(['id', 'event_type_scope_id', 'scope', 'alliance_id', 'kingdom_id', 'player_id'])
            ->whereKey($event->id)
            ->firstOrFail();

        $typeScope = EventTypeScope::query()
            ->whereKey($route->event_type_scope_id)
            ->where('scope', $route->scope->value)
            ->sharedLock()
            ->firstOrFail();

        [$currentActor, $target] = match ($route->scope) {
            EventScope::Alliance => $this->lockAllianceTarget($actor, $route),
            EventScope::Kingdom => $this->lockKingdomTarget($actor, $route),
            EventScope::Player => $this->lockPlayerTarget($actor, $route),
        };

        $query = Event::query()->whereKey($route->id);
        $lockedEvent = $exclusiveEvent
            ? $query->lockForUpdate()->firstOrFail()
            : $query->sharedLock()->firstOrFail();

        if ($lockedEvent->scope !== $route->scope
            || (string) $lockedEvent->event_type_scope_id !== (string) $route->event_type_scope_id
            || (string) ($lockedEvent->alliance_id ?? '') !== (string) ($route->alliance_id ?? '')
            || (string) ($lockedEvent->kingdom_id ?? '') !== (string) ($route->kingdom_id ?? '')
            || (string) ($lockedEvent->player_id ?? '') !== (string) ($route->player_id ?? '')) {
            throw new AuthorizationException('The Event target changed while the write was being prepared.');
        }

        return new EventMutationContext($lockedEvent, $typeScope, $currentActor, $target);
    }

    public function lockSelfScope(Player $actor, Event $event, Player $participant): EventMutationContext
    {
        $context = $this->lockEventScope($actor, $event);

        if ($context->event->scope === EventScope::Alliance) {
            if (! $context->target instanceof Alliance
                || ! AllianceRosterEntry::query()
                    ->where('alliance_id', $context->target->id)
                    ->where('player_id', $participant->id)
                    ->where('state', RosterState::Active->value)
                    
                    ->exists()) {
                throw new AuthorizationException;
            }
        }

        return $context;
    }

    public function lockCreationScope(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
    ): EventCreationMutationContext {
        $this->assertTransaction();

        $scope = $this->targets->scopeFor($target);
        $currentConfiguration = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->where('scope', $scope->value)
            
            ->firstOrFail();

        [$currentActor, $currentTarget] = match ($scope) {
            EventScope::Alliance => $this->lockAllianceCreationTarget($actor, $target),
            EventScope::Kingdom => $this->lockKingdomCreationTarget($actor, $target),
            EventScope::Player => $this->lockPlayerCreationTarget($actor, $target),
        };

        return new EventCreationMutationContext($currentConfiguration, $currentActor, $currentTarget);
    }

    /** @return array{Player,Alliance} */
    private function lockAllianceTarget(Player $actor, Event $event): array
    {
        $alliance = Alliance::query()->whereKey($event->alliance_id)->firstOrFail();
        $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);

        return [$context->actor, $context->alliance];
    }

    /** @return array{Player,Kingdom} */
    private function lockKingdomTarget(Player $actor, Event $event): array
    {
        $kingdom = Kingdom::query()->whereKey($event->kingdom_id)->firstOrFail();
        $context = $this->kingdomWriteState->lockActiveScope($actor, $kingdom);

        return [$context->actor, $context->kingdom];
    }

    /** @return array{Player,Player} */
    private function lockPlayerTarget(Player $actor, Event $event): array
    {
        $actorContext = $this->playerWriteState->lockActor($actor);
        $currentActor = $actorContext->actor;
        $currentTarget = Player::query()
            ->whereKey($event->player_id)
            
            ->firstOrFail();

        if ((string) $currentActor->id !== (string) $currentTarget->id) {
            $entries = AllianceRosterEntry::query()
                ->where('player_id', $currentTarget->id)
                ->where('state', RosterState::Active->value)
                ->orderBy('alliance_id')
                
                ->get();

            foreach ($entries as $entry) {
                $alliance = Alliance::query()->whereKey($entry->alliance_id)->first();
                if (! $alliance instanceof Alliance
                    || (string) $alliance->kingdom_id !== (string) $currentTarget->current_kingdom_id) {
                    continue;
                }

                try {
                    $this->allianceWriteState->lockActiveScope($currentActor, $alliance);
                } catch (AuthorizationException) {
                    // No active actor scope in this candidate Alliance. Permission
                    // evaluation later may only succeed through stabilized scopes.
                }
            }
        }

        return [$currentActor, $currentTarget];
    }

    /** @return array{Player,Alliance} */
    private function lockAllianceCreationTarget(Player $actor, Alliance|Kingdom|Player $target): array
    {
        if (! $target instanceof Alliance) {
            throw new LogicException('Alliance Event scope requires an Alliance target.');
        }

        $context = $this->allianceWriteState->lockActiveScope($actor, $target);

        return [$context->actor, $context->alliance];
    }

    /** @return array{Player,Kingdom} */
    private function lockKingdomCreationTarget(Player $actor, Alliance|Kingdom|Player $target): array
    {
        if (! $target instanceof Kingdom) {
            throw new LogicException('Kingdom Event scope requires a Kingdom target.');
        }

        $context = $this->kingdomWriteState->lockActiveScope($actor, $target);

        return [$context->actor, $context->kingdom];
    }

    /** @return array{Player,Player} */
    private function lockPlayerCreationTarget(Player $actor, Alliance|Kingdom|Player $target): array
    {
        if (! $target instanceof Player) {
            throw new LogicException('Player Event scope requires a Player target.');
        }

        $actorContext = $this->playerWriteState->lockActor($actor);
        $currentTarget = (string) $target->id === (string) $actorContext->actor->id
            ? $actorContext->actor
            : Player::query()->whereKey($target->id)->firstOrFail();

        return [$actorContext->actor, $currentTarget];
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event write state must be acquired inside a database transaction.');
        }
    }
}
