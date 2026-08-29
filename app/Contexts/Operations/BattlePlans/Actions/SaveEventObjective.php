<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Actions;

use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventObjective
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $metadata */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $name,
        string $objectiveType = 'custom',
        ?string $description = null,
        int $priority = 50,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        EventObjectiveStatus $status = EventObjectiveStatus::Planned,
        int $sortOrder = 0,
        array $metadata = [],
        ?string $parentId = null,
        ?string $objectiveId = null,
    ): void {
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
        if ($startsAt !== null && $endsAt !== null && ! $endsAt->greaterThan($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'Objective end must be after objective start.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $name, $objectiveType, $description, $priority, $startsAt, $endsAt, $status, $sortOrder, $metadata, $parentId, $objectiveId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::BattleAssignments);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($startsAt !== null && $endsAt !== null
                && ($startsAt->lessThan($occurrence->starts_at) || $endsAt->greaterThan($occurrence->ends_at))) {
                throw ValidationException::withMessages(['starts_at' => 'Objective timing must stay within the Event occurrence.']);
            }

            $record = $objectiveId !== null
                ? EventObjective::query()->whereKey($objectiveId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventObjective(['occurrence_id' => $occurrence->id]);

            $parent = null;
            if ($parentId !== null) {
                $parent = EventObjective::query()->whereKey($parentId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
                if ($record->exists
                    && ((string) $record->id === (string) $parent->id || $this->isDescendant($parent, $record))) {
                    throw ValidationException::withMessages(['parent_id' => 'An objective cannot be nested beneath itself or one of its descendants.']);
                }
            }

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actorPlayerId;
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
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $eventName = $created ? 'event.objective.created' : 'event.objective.updated';
            $auditMetadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'objective_id' => (string) $record->id,
                'parent_id' => $record->parent_id === null ? null : (string) $record->parent_id,
                'status' => $record->status->value,
                'priority' => (int) $record->priority,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record($eventName, $context->actor, $record, $context->target->allianceId, $auditMetadata);
            $this->outbox->record($eventName, $context->target->allianceId, $record, $auditMetadata, partitionKey: $context->target->partitionKey());
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
