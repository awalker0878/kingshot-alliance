<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventObjectiveAssignment
{
    public function __construct(
        private EventAuthorization $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventObjectiveAssignment $assignment): void
    {
        $assignment->loadMissing('objective.occurrence.event');
        $objective = $assignment->objective;
        $occurrence = $objective->occurrence;
        $event = $occurrence->event;

        DB::transaction(function () use ($actor, $assignment, $objective, $occurrence, $event): void {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Objectives);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $lockedObjective = EventObjective::query()
                ->whereKey($objective->id)
                ->where('occurrence_id', $lockedOccurrence->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = EventObjectiveAssignment::query()
                ->whereKey($assignment->id)
                ->where('objective_id', $lockedObjective->id)
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'objective_id' => (string) $lockedObjective->id,
                'assignment_id' => (string) $locked->id,
                'roster_id' => $locked->roster_id === null ? null : (string) $locked->roster_id,
                'player_id' => $locked->player_id === null ? null : (string) $locked->player_id,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $this->audit->record('event.objective.assignment_removed', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.objective.assignment_removed',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );
            $locked->delete();
        });
    }
}
