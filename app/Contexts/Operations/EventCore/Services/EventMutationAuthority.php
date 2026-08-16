<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Governance\Services\KingdomMutationAuthority;
use App\Contexts\GameWorld\Governance\Services\PlayerMutationAuthority;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsMutationAuthority;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\EventCore\ValueObjects\EventMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Resolves current Event scope authority for state-changing Event/Rally workflows.
 * Business aggregates remain owned and locked by the calling domain.
 */
final readonly class EventMutationAuthority
{
    public function __construct(
        private EventAuthorization $authorization,
        private AllianceOperationsMutationAuthority $allianceAuthority,
        private KingdomMutationAuthority $kingdomAuthority,
        private PlayerMutationAuthority $playerAuthority,
    ) {}

    public function requireManager(Player $actor, Event $event): EventMutationContext
    {
        return $this->requireManagerWithEventLock($actor, $event, false);
    }

    /** Use only when the caller owns an Event-aggregate mutation. */
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
            if (! $target instanceof Alliance) {
                throw new LogicException('Alliance Event mutation context must resolve an Alliance target.');
            }

            if ((string) $currentActor->current_kingdom_id !== (string) $target->kingdom_id
                || ! AllianceRosterEntry::query()
                    ->where('alliance_id', $target->id)
                    ->where('player_id', $currentActor->id)
                    ->where('state', RosterState::Active->value)
                    ->sharedLock()
                    ->exists()) {
                throw new AuthorizationException;
            }

            return new EventMutationContext($currentEvent, $typeScope, $currentActor, $target);
        }

        if ($currentEvent->scope === EventScope::Kingdom) {
            if (! $target instanceof Kingdom) {
                throw new LogicException('Kingdom Event mutation context must resolve a Kingdom target.');
            }

            if ((string) $currentActor->current_kingdom_id !== (string) $target->id) {
                throw new AuthorizationException;
            }

            return new EventMutationContext($currentEvent, $typeScope, $currentActor, $target);
        }

        return new EventMutationContext($currentEvent, $typeScope, $currentActor, $target);
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
            throw new LogicException('Event mutation authority must be acquired inside a database transaction.');
        }
    }

    private function freshRoute(Event $event): Event
    {
        return Event::query()
            ->select([
                'id',
                'event_type_scope_id',
                'scope',
                'alliance_id',
                'kingdom_id',
                'player_id',
            ])
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
        if (! $this->authorization->supports($event->scope, $permission)) {
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
        $context = $this->allianceAuthority->require($actor, $alliance, $permission);

        return [$context->actor, $context->alliance];
    }

    /** @return array{Player,Kingdom} */
    private function authorizeKingdomScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        $kingdom = Kingdom::query()->whereKey($event->kingdom_id)->firstOrFail();
        $context = $this->kingdomAuthority->require($actor, $kingdom, $permission);

        return [$context->actor, $context->kingdom];
    }

    /** @return array{Player,Player} */
    private function authorizePlayerScope(Player $actor, Event $event, OperationsPermission $permission): array
    {
        $target = Player::query()->whereKey($event->player_id)->firstOrFail();

        if ((string) $actor->id === (string) $target->id) {
            $context = $this->playerAuthority->require($actor);

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
                $managerContext = $this->allianceAuthority->require($actor, $alliance, $permission);
            } catch (AuthorizationException) {
                continue;
            }

            $currentTarget = Player::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

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
            throw new AuthorizationException('The Event authority target changed while the mutation was being prepared.');
        }

        return $locked;
    }
}
