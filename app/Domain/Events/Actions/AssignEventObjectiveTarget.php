<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventObjective;
use App\Domain\Events\Models\EventObjectiveAssignment;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignEventObjectiveTarget
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventObjective $objective, EventRoster|Player $assignmentTarget, ?string $notes = null): EventObjectiveAssignment
    {
        $objective->loadMissing('occurrence.event');
        $occurrence = $objective->occurrence;
        $event = $occurrence->event;

        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Assignment notes must be 10000 characters or fewer.']);
        }
        if ($assignmentTarget instanceof EventRoster && (string) $assignmentTarget->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }

        return DB::transaction(function () use ($actor, $objective, $occurrence, $event, $assignmentTarget, $notes): EventObjectiveAssignment {
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

            $targetRoster = null;
            $targetPlayer = null;
            if ($assignmentTarget instanceof EventRoster) {
                $targetRoster = EventRoster::query()
                    ->whereKey($assignmentTarget->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->sharedLock()
                    ->firstOrFail();
            } else {
                $targetPlayer = Player::query()
                    ->whereKey($assignmentTarget->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $this->participants->eligible($context->event, $targetPlayer)) {
                    throw ValidationException::withMessages([
                        'player' => 'This Player is not eligible for the Event target.',
                    ]);
                }
            }

            $query = EventObjectiveAssignment::query()->where('objective_id', $lockedObjective->id);
            if ($targetRoster instanceof EventRoster) {
                $query->where('roster_id', $targetRoster->id);
            } else {
                $query->where('player_id', $targetPlayer?->id);
            }

            $assignment = $query->lockForUpdate()->first();
            $created = ! ($assignment instanceof EventObjectiveAssignment);
            $assignment ??= new EventObjectiveAssignment([
                'objective_id' => $lockedObjective->id,
                'occurrence_id' => $lockedOccurrence->id,
            ]);
            $assignment->forceFill([
                'roster_id' => $targetRoster?->id,
                'player_id' => $targetPlayer?->id,
                'assigned_by_player_id' => $context->actor->id,
                'assigned_at' => now(),
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $eventName = $created ? 'event.objective.assignment_created' : 'event.objective.assignment_updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'objective_id' => (string) $lockedObjective->id,
                'assignment_id' => (string) $assignment->id,
                'roster_id' => $assignment->roster_id === null ? null : (string) $assignment->roster_id,
                'player_id' => $assignment->player_id === null ? null : (string) $assignment->player_id,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $assignment, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $assignment,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $assignment->refresh()->load(['roster', 'player']);
        });
    }
}
