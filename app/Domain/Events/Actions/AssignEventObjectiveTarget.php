<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventObjective;
use App\Domain\Events\Models\EventObjectiveAssignment;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignEventObjectiveTarget
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventObjective $objective, EventRoster|Player $assignmentTarget, ?string $notes = null): EventObjectiveAssignment
    {
        $objective->loadMissing('occurrence.event.typeScope');
        $occurrence = $objective->occurrence;
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Objectives);
        $this->authorization->authorizeManager($actor, $event);

        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Assignment notes must be 10000 characters or fewer.']);
        }
        if ($assignmentTarget instanceof EventRoster && (string) $assignmentTarget->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }
        if ($assignmentTarget instanceof Player && ! $this->authorization->eligible($event, $assignmentTarget)) {
            throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
        }

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $objective, $occurrence, $event, $assignmentTarget, $notes, $target): EventObjectiveAssignment {
            EventObjective::query()->whereKey($objective->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
            $query = EventObjectiveAssignment::query()->where('objective_id', $objective->id);
            if ($assignmentTarget instanceof EventRoster) {
                $query->where('roster_id', $assignmentTarget->id);
            } else {
                $query->where('player_id', $assignmentTarget->id);
            }
            $assignment = $query->lockForUpdate()->first();
            $created = ! ($assignment instanceof EventObjectiveAssignment);
            $assignment ??= new EventObjectiveAssignment([
                'objective_id' => $objective->id,
                'occurrence_id' => $occurrence->id,
            ]);
            $assignment->forceFill([
                'roster_id' => $assignmentTarget instanceof EventRoster ? $assignmentTarget->id : null,
                'player_id' => $assignmentTarget instanceof Player ? $assignmentTarget->id : null,
                'assigned_by_player_id' => $actor->id,
                'assigned_at' => now(),
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $eventName = $created ? 'event.objective.assignment_created' : 'event.objective.assignment_updated';
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'objective_id' => (string) $objective->id,
                'assignment_id' => (string) $assignment->id,
                'roster_id' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                'player_id' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $assignment, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $assignment, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $assignment->refresh()->load(['roster', 'player']);
        });
    }
}
