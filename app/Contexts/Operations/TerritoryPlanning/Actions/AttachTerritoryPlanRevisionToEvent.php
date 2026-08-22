<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\EventTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AttachTerritoryPlanRevisionToEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthorization,
        private TerritoryPlanWriteState $territoryWriteState,
        private TerritoryPlanningAuthorization $territoryAuthorization,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $revisionId,
        string $purpose = 'positioning',
    ): string {
        if (! preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $purpose)) {
            throw ValidationException::withMessages([
                'purpose' => 'Territory plan purpose is invalid.',
            ]);
        }

        return DB::transaction(function () use (
            $actorPlayerId,
            $occurrenceId,
            $revisionId,
            $purpose,
        ): string {
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            $eventContext = $this->eventWriteState->lockEventScope(
                $actorPlayerId,
                (string) $occurrence->event_id,
            );
            $this->eventAuthorization->authorizeManager($eventContext);
            if ($eventContext->target->scope === EventScope::Player) {
                throw ValidationException::withMessages([
                    'territory_plan_revision_id' => 'Player-scoped Events cannot attach an Alliance or Kingdom territory plan.',
                ]);
            }

            $revisionRoute = TerritoryPlanRevision::query()
                ->select(['id', 'territory_plan_id'])
                ->whereKey($revisionId)
                ->firstOrFail();
            $territoryContext = $this->territoryWriteState->lock(
                $actorPlayerId,
                (string) $revisionRoute->territory_plan_id,
            );
            $this->territoryAuthorization->authorizeView($territoryContext);
            $plan = $territoryContext->plan;

            $revision = TerritoryPlanRevision::query()
                ->whereKey($revisionId)
                ->where('territory_plan_id', $plan->id)
                ->sharedLock()
                ->firstOrFail();

            if ((string) $plan->kingdom_id !== $eventContext->target->kingdomId) {
                throw new AuthorizationException;
            }
            if (
                $eventContext->target->scope === EventScope::Alliance
                && (
                    $plan->scope !== TerritoryPlanScope::Alliance
                    || (string) $plan->owner_alliance_id !== $eventContext->target->allianceId
                )
            ) {
                throw ValidationException::withMessages([
                    'territory_plan_revision_id' => 'Alliance Events require a published revision owned by the same Alliance.',
                ]);
            }
            if (
                $eventContext->target->scope === EventScope::Kingdom
                && $plan->scope !== TerritoryPlanScope::Kingdom
            ) {
                throw ValidationException::withMessages([
                    'territory_plan_revision_id' => 'Kingdom Events require a Kingdom-scoped published territory-plan revision.',
                ]);
            }

            $link = EventTerritoryPlanRevision::query()->updateOrCreate(
                [
                    'event_occurrence_id' => $occurrenceId,
                    'purpose' => $purpose,
                ],
                [
                    'territory_plan_revision_id' => (string) $revision->id,
                    'created_by_player_id' => $actorPlayerId,
                    'created_at' => now(),
                ],
            );
            $this->audit->record(
                'territory.plan.event_revision_attached',
                $eventContext->actor,
                $occurrence,
                $eventContext->target->allianceId,
                [
                    'territory_plan_revision_id' => (string) $revision->id,
                    'territory_plan_id' => (string) $plan->id,
                    'purpose' => $purpose,
                ],
            );

            return (string) $link->id;
        });
    }
}
