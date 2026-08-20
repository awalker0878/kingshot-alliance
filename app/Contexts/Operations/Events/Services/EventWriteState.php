<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\ValueObjects\EventCreationMutationContext;
use App\Contexts\Operations\Events\ValueObjects\EventMutationContext;
use App\Contexts\Operations\Events\ValueObjects\EventScopeAuthorityFacts;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Policy-free transaction-time state acquisition for Event write paths.
 *
 * Cross-context models never enter this service. Current identity and mutable
 * authority are reacquired from their owning contexts while the protected
 * transaction is active; only Operations-owned Eloquent rows are returned as
 * mutation state.
 */
final readonly class EventWriteState
{
    public function __construct(
        private EventTargetResolver $targets,
        private PlayerReferenceQuery $players,
        private AllianceAuthorityFactsQuery $allianceAuthority,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
    ) {}

    public function lockEventScope(
        string $actorPlayerId,
        string $eventId,
        bool $exclusiveEvent = false,
    ): EventMutationContext {
        $this->assertTransaction();

        $route = Event::query()
            ->select(['id', 'event_type_scope_id', 'scope', 'alliance_id', 'kingdom_id', 'player_id'])
            ->whereKey($eventId)
            ->firstOrFail();
        $scope = $route->scopeEnum();

        $typeScope = EventTypeScope::query()
            ->whereKey($route->event_type_scope_id)
            ->where('scope', $scope->value)
            ->sharedLock()
            ->firstOrFail();

        $actor = $this->players->lockCurrent($actorPlayerId);
        $target = $this->targets->lockCurrent($scope, $this->targetId($route, $scope));
        $authority = $this->lockAuthority($actor, $target);

        $query = Event::query()->whereKey($route->id);
        $lockedEvent = $exclusiveEvent
            ? $query->lockForUpdate()->firstOrFail()
            : $query->sharedLock()->firstOrFail();

        if ($lockedEvent->scopeEnum() !== $scope
            || (string) $lockedEvent->event_type_scope_id !== (string) $route->event_type_scope_id
            || (string) ($lockedEvent->alliance_id ?? '') !== (string) ($route->alliance_id ?? '')
            || (string) ($lockedEvent->kingdom_id ?? '') !== (string) ($route->kingdom_id ?? '')
            || (string) ($lockedEvent->player_id ?? '') !== (string) ($route->player_id ?? '')) {
            throw new AuthorizationException('The Event target changed while the write was being prepared.');
        }

        return new EventMutationContext($lockedEvent, $typeScope, $actor, $target, $authority);
    }

    public function lockSelfScope(
        string $actorPlayerId,
        string $eventId,
        string $participantPlayerId,
    ): EventMutationContext {
        $context = $this->lockEventScope($actorPlayerId, $eventId);

        if ($context->actor->playerId !== $participantPlayerId) {
            throw new AuthorizationException;
        }

        if ($context->event->scopeEnum() === EventScope::Alliance) {
            if ($context->target->allianceId === null
                || ! $this->memberships->lockActiveMember($context->target->allianceId, $participantPlayerId)) {
                throw new AuthorizationException;
            }
        }

        if ($context->event->scopeEnum() === EventScope::Player
            && $context->target->playerId !== $participantPlayerId) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function lockCreationScope(
        string $actorPlayerId,
        string $configurationId,
        EventScope $scope,
        string $targetId,
    ): EventCreationMutationContext {
        $this->assertTransaction();

        $configuration = EventTypeScope::query()
            ->whereKey($configurationId)
            ->where('scope', $scope->value)
            ->sharedLock()
            ->firstOrFail();

        $actor = $this->players->lockCurrent($actorPlayerId);
        $target = $this->targets->lockCurrent($scope, $targetId);
        $authority = $this->lockAuthority($actor, $target);

        return new EventCreationMutationContext($configuration, $actor, $target, $authority);
    }

    private function lockAuthority(PlayerReference $actor, EventTargetReference $target): EventScopeAuthorityFacts
    {
        return match ($target->scope) {
            EventScope::Alliance => new EventScopeAuthorityFacts(
                allianceFacts: $target->allianceId === null
                    ? null
                    : $this->allianceAuthority->lockCurrent($actor->playerId, $target->allianceId),
            ),
            EventScope::Kingdom => new EventScopeAuthorityFacts(
                kingdomFacts: $target->kingdomId === null
                    ? null
                    : $this->kingdomAuthority->lockCurrent($actor->playerId, $target->kingdomId),
            ),
            EventScope::Player => new EventScopeAuthorityFacts(
                playerManagerAllianceFacts: $this->lockPlayerManagerAllianceFacts($actor, $target),
            ),
        };
    }

    /** @return list<AllianceAuthorityFacts> */
    private function lockPlayerManagerAllianceFacts(PlayerReference $actor, EventTargetReference $target): array
    {
        if ($target->playerId === null || $target->kingdomId === null || $actor->playerId === $target->playerId) {
            return [];
        }

        $facts = [];
        foreach ($this->roster->lockActiveAllianceIdsForPlayerInKingdom($target->playerId, $target->kingdomId) as $allianceId) {
            $authority = $this->allianceAuthority->lockCurrent($actor->playerId, $allianceId);
            if ($authority instanceof AllianceAuthorityFacts) {
                $facts[] = $authority;
            }
        }

        return $facts;
    }

    private function targetId(Event $event, EventScope $scope): string
    {
        $targetId = match ($scope) {
            EventScope::Alliance => $event->alliance_id,
            EventScope::Kingdom => $event->kingdom_id,
            EventScope::Player => $event->player_id,
        };

        if (! is_string($targetId) || $targetId === '') {
            throw new LogicException('An Event must contain exactly one valid target identity.');
        }

        return $targetId;
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event write state must be acquired inside a database transaction.');
        }
    }
}
