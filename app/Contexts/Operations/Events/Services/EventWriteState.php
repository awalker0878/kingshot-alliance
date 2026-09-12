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

        [$actor, $target, $authority] = $this->lockTargetAndActor($actorPlayerId, $scope, $this->targetId($route, $scope));

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

        [$actor, $target, $authority] = $this->lockTargetAndActor($actorPlayerId, $scope, $targetId);

        return new EventCreationMutationContext($configuration, $actor, $target, $authority);
    }

    /** @return array{PlayerReference,EventTargetReference,EventScopeAuthorityFacts} */
    private function lockTargetAndActor(string $actorPlayerId, EventScope $scope, string $targetId): array
    {
        $manager = null;
        $playerRoute = null;
        if ($scope === EventScope::Player) {
            $playerRoute = $this->players->require($targetId);
            if ($actorPlayerId !== $targetId) {
                // The canonical partial unique index permits one active Alliance
                // membership per Governor. Never scan all of the target's rosters.
                $allianceIds = $this->memberships->activeAllianceIdsForPlayerInKingdom($actorPlayerId, $playerRoute->kingdomId);
                if ($allianceIds !== []) {
                    $manager = $this->allianceAuthority->lockCurrent($actorPlayerId, $allianceIds[0]);
                }
            }
        }

        $target = $this->targets->lockScope($scope, $targetId);
        $allianceFacts = $scope === EventScope::Alliance && $target->allianceId !== null
            ? $this->allianceAuthority->lockCurrent($actorPlayerId, $target->allianceId) : null;
        $kingdomFacts = $scope === EventScope::Kingdom && $target->kingdomId !== null
            ? $this->kingdomAuthority->lockCurrent($actorPlayerId, $target->kingdomId) : null;

        $ids = $scope === EventScope::Player ? array_values(array_unique([$actorPlayerId, $targetId])) : [$actorPlayerId];
        sort($ids);
        $lockedPlayers = [];
        foreach ($ids as $id) {
            $lockedPlayers[$id] = $this->players->lockCurrent($id);
        }
        $actor = $lockedPlayers[$actorPlayerId];
        if ($actor->kingdomId !== $target->kingdomId) {
            throw new AuthorizationException('The current Governor belongs to another Kingdom.');
        }
        $managerFacts = [];
        if ($scope === EventScope::Player) {
            if ($playerRoute === null || $lockedPlayers[$targetId]->kingdomId !== $target->kingdomId
                || $playerRoute->kingdomId !== $target->kingdomId) {
                throw new AuthorizationException('The Player target changed while its scope was being acquired.');
            }
            $target = $this->targets->resolve($scope, $targetId);
            if ($manager instanceof AllianceAuthorityFacts && $manager->kingdomId === $target->kingdomId
                && $this->roster->lockActiveRosterPresence($manager->allianceId, $targetId)) {
                $managerFacts[] = $manager;
            }
        }

        return [$actor, $target, new EventScopeAuthorityFacts($allianceFacts, $kingdomFacts, $managerFacts)];
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
