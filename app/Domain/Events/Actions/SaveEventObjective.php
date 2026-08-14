<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventObjectiveStatus;
use App\Domain\Events\Models\EventObjective;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventObjective
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $metadata */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        string $name,
        string $objectiveType = 'custom',
        ?string $description = null,
        int $priority = 50,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        EventObjectiveStatus $status = EventObjectiveStatus::Planned,
        int $sortOrder = 0,
        array $metadata = [],
        ?EventObjective $parent = null,
        ?EventObjective $objective = null,
    ): EventObjective {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Objectives);
        $this->authorization->authorizeManager($actor, $event);

        $name = trim($name);
        $objectiveType = trim($objectiveType);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'Objective name is required and must be 160 characters or fewer.']);
        }
        if (! preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $objectiveType) || mb_strlen($objectiveType) > 64) {
            throw ValidationException::withMessages(['objective_type' => 'Objective type must use lowercase letters, numbers, hyphens, or underscores.']);
        }
        if ($description !== null && mb_strlen(trim($description)) > 10000) {
            throw ValidationException::withMessages(['description' => 'Objective description must be 10000 characters or fewer.']);
        }
        if ($priority < 0 || $priority > 100) {
            throw ValidationException::withMessages(['priority' => 'Objective priority must be between 0 and 100.']);
        }
        if (($startsAt === null) !== ($endsAt === null)) {
            throw ValidationException::withMessages(['starts_at' => 'Objective start and end must both be provided or both be empty.']);
        }
        if ($startsAt !== null && $endsAt !== null) {
            if (! $endsAt->greaterThan($startsAt)) {
                throw ValidationException::withMessages(['ends_at' => 'Objective end must be after objective start.']);
            }
            if ($startsAt->lessThan($occurrence->starts_at) || $endsAt->greaterThan($occurrence->ends_at)) {
                throw ValidationException::withMessages(['starts_at' => 'Objective timing must stay within the Event occurrence.']);
            }
        }
        if ($parent instanceof EventObjective && (string) $parent->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }
        if ($objective instanceof EventObjective && (string) $objective->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }
        if ($objective instanceof EventObjective && $parent instanceof EventObjective) {
            if ((string) $objective->id === (string) $parent->id || $this->isDescendant($parent, $objective)) {
                throw ValidationException::withMessages(['parent_id' => 'An objective cannot be nested beneath itself or one of its descendants.']);
            }
        }

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $name, $objectiveType, $description, $priority, $startsAt, $endsAt, $status, $sortOrder, $metadata, $parent, $objective, $target): EventObjective {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $record = $objective instanceof EventObjective
                ? EventObjective::query()->whereKey($objective->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventObjective(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->id;
            }

            $record->forceFill([
                'parent_id' => $parent?->id,
                'objective_type' => $objectiveType,
                'name' => $name,
                'description' => $description === null || trim($description) === '' ? null : trim($description),
                'priority' => $priority,
                'starts_at' => $startsAt?->utc(),
                'ends_at' => $endsAt?->utc(),
                'status' => $status,
                'sort_order' => max(0, $sortOrder),
                'metadata' => $metadata,
                'updated_by_player_id' => $actor->id,
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $eventName = $created ? 'event.objective.created' : 'event.objective.updated';
            $auditMetadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'objective_id' => (string) $record->id,
                'parent_id' => $record->parent_id === null ? null : (string) $record->parent_id,
                'status' => $record->status->value,
                'priority' => (int) $record->priority,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $record, $alliance, $auditMetadata);
            $this->outbox->record($eventName, $alliance?->id, $record, $auditMetadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }

    private function isDescendant(EventObjective $candidateParent, EventObjective $objective): bool
    {
        $cursor = $candidateParent;
        $visited = [];
        while ($cursor->parent_id !== null) {
            $cursorId = (string) $cursor->id;
            if (isset($visited[$cursorId])) {
                return true;
            }
            $visited[$cursorId] = true;

            if ((string) $cursor->parent_id === (string) $objective->id) {
                return true;
            }
            $next = EventObjective::query()->whereKey($cursor->parent_id)->first();
            if (! $next instanceof EventObjective) {
                return false;
            }
            $cursor = $next;
        }

        return false;
    }
}
