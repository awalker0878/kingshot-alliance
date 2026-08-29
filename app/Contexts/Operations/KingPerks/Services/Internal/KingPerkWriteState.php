<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services\Internal;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Events\ValueObjects\EventMutationContext;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingPerkRequest;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @internal Transaction-time state acquisition for King Perks writes.
 *
 * Public application boundaries pass IDs. The Eloquent models returned here
 * are Operations-owned rows loaded by the protected operation itself and must
 * not escape King Perks implementation services as authority state.
 */
final readonly class KingPerkWriteState
{
    public function __construct(
        private EventWriteState $events,
        private EventAuthorization $authorization,
    ) {}

    public function managerEvent(string $actorPlayerId, string $eventId): EventMutationContext
    {
        $context = $this->events->lockEventScope($actorPlayerId, $eventId);
        $this->authorization->authorizeManager($context);
        if ($context->target->scope !== EventScope::Kingdom || $context->target->kingdomId === null) {
            throw new AuthorizationException('King Perks require a Kingdom Event target.');
        }

        return $context;
    }

    /** @return array{KingPerkPlan, Event, PlayerReference} */
    public function managerPlan(string $actorPlayerId, string $planId): array
    {
        $route = KingPerkPlan::query()
            ->select(['id', 'event_id', 'kingdom_id'])
            ->whereKey($planId)
            ->firstOrFail();

        $context = $this->events->lockEventScope($actorPlayerId, (string) $route->event_id);
        $this->authorization->authorizeManager($context);
        $this->assertKingdomTarget($context->target->scope, $context->target->kingdomId, (string) $route->kingdom_id);

        $plan = KingPerkPlan::query()
            ->whereKey($route->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $route->kingdom_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$plan, $context->event, $context->actor];
    }

    /** @return array{KingPerkPlan, Event, PlayerReference} */
    public function selfPlan(string $actorPlayerId, string $planId): array
    {
        $route = KingPerkPlan::query()
            ->select(['id', 'event_id', 'kingdom_id'])
            ->whereKey($planId)
            ->firstOrFail();

        $context = $this->events->lockSelfScope($actorPlayerId, (string) $route->event_id, $actorPlayerId);
        $this->authorization->authorizeSelf($context, $actorPlayerId);
        $this->assertKingdomTarget($context->target->scope, $context->target->kingdomId, (string) $route->kingdom_id);

        if ($context->actor->kingdomId !== (string) $route->kingdom_id) {
            throw new AuthorizationException;
        }

        $plan = KingPerkPlan::query()
            ->whereKey($route->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $route->kingdom_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$plan, $context->event, $context->actor];
    }

    /** @return array{KingPerkAppointment, KingPerkPlan, Event, PlayerReference} */
    public function managerAppointment(string $actorPlayerId, string $appointmentId): array
    {
        $route = KingPerkAppointment::query()
            ->select(['id', 'plan_id'])
            ->whereKey($appointmentId)
            ->firstOrFail();
        [$plan, $event, $actor] = $this->managerPlan($actorPlayerId, (string) $route->plan_id);

        $appointment = KingPerkAppointment::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$appointment, $plan, $event, $actor];
    }

    /** @return array{KingPerkAppointment, KingPerkPlan, Event, PlayerReference} */
    public function selfAppointment(string $actorPlayerId, string $appointmentId): array
    {
        $route = KingPerkAppointment::query()
            ->select(['id', 'plan_id', 'assigned_player_id'])
            ->whereKey($appointmentId)
            ->firstOrFail();
        [$plan, $event, $actor] = $this->selfPlan($actorPlayerId, (string) $route->plan_id);

        if ((string) $route->assigned_player_id !== $actor->playerId) {
            throw new AuthorizationException;
        }

        $appointment = KingPerkAppointment::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->where('assigned_player_id', $actor->playerId)
            ->lockForUpdate()
            ->firstOrFail();

        return [$appointment, $plan, $event, $actor];
    }

    /** @return array{KingPerkRequest, KingPerkPlan, Event, PlayerReference} */
    public function managerRequest(string $actorPlayerId, string $requestId): array
    {
        $route = KingPerkRequest::query()
            ->select(['id', 'plan_id'])
            ->whereKey($requestId)
            ->firstOrFail();
        [$plan, $event, $actor] = $this->managerPlan($actorPlayerId, (string) $route->plan_id);

        $request = KingPerkRequest::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$request, $plan, $event, $actor];
    }

    /** @return array{KingPerkRequest, KingPerkPlan, Event, PlayerReference} */
    public function selfRequest(string $actorPlayerId, string $requestId): array
    {
        $route = KingPerkRequest::query()
            ->select(['id', 'plan_id', 'player_id'])
            ->whereKey($requestId)
            ->firstOrFail();
        [$plan, $event, $actor] = $this->selfPlan($actorPlayerId, (string) $route->plan_id);

        if ((string) $route->player_id !== $actor->playerId) {
            throw new AuthorizationException;
        }

        $request = KingPerkRequest::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->where('player_id', $actor->playerId)
            ->lockForUpdate()
            ->firstOrFail();

        return [$request, $plan, $event, $actor];
    }

    /** @return array{KingSkillPlan, KingPerkPlan, Event, PlayerReference} */
    public function managerSkill(string $actorPlayerId, string $skillId): array
    {
        $route = KingSkillPlan::query()
            ->select(['id', 'plan_id'])
            ->whereKey($skillId)
            ->firstOrFail();
        [$plan, $event, $actor] = $this->managerPlan($actorPlayerId, (string) $route->plan_id);

        $skill = KingSkillPlan::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$skill, $plan, $event, $actor];
    }

    private function assertKingdomTarget(EventScope $scope, ?string $targetKingdomId, string $planKingdomId): void
    {
        if ($scope !== EventScope::Kingdom || $targetKingdomId === null || $targetKingdomId !== $planKingdomId) {
            throw new AuthorizationException('King Perks require the current Kingdom Event target.');
        }
    }
}
