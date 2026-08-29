<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventRoster
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $settings */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $key,
        EventRosterType $type,
        string $assignmentGroup,
        ?string $name = null,
        ?string $nameKey = null,
        ?int $capacity = null,
        int $sortOrder = 0,
        array $settings = [],
        ?string $parentId = null,
        ?string $rosterId = null,
    ): void {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key)) {
            throw ValidationException::withMessages(['key' => 'Roster key must use lowercase letters, numbers, and hyphens.']);
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $assignmentGroup)) {
            throw ValidationException::withMessages(['assignment_group' => 'Assignment group must use lowercase letters, numbers, and hyphens.']);
        }
        if (($name === null || trim($name) === '') && ($nameKey === null || trim($nameKey) === '')) {
            throw ValidationException::withMessages(['name' => 'A roster name is required.']);
        }
        if ($capacity !== null && $capacity < 1) {
            throw ValidationException::withMessages(['capacity' => 'Roster capacity must be at least one.']);
        }

        $occupying = array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        );

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $key, $type, $assignmentGroup, $name, $nameKey, $capacity, $sortOrder, $settings, $parentId, $rosterId, $occupying): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Roster);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $record = $rosterId !== null
                ? EventRoster::query()->whereKey($rosterId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventRoster(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;

            $parent = null;
            if ($parentId !== null) {
                $parent = EventRoster::query()->whereKey($parentId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
                if ((string) $parent->assignment_group !== $assignmentGroup) {
                    throw ValidationException::withMessages(['parent_id' => 'A parent roster must belong to the same assignment group.']);
                }
                if ($record->exists && $this->isDescendant($parent, $record)) {
                    throw ValidationException::withMessages(['parent_id' => 'A roster cannot be nested beneath itself or one of its descendants.']);
                }
            }

            if ($record->exists && (string) $record->assignment_group !== $assignmentGroup
                && ($record->children()->exists() || $record->members()->whereIn('status', $occupying)->exists())) {
                throw ValidationException::withMessages([
                    'assignment_group' => 'Assignment group cannot change while the roster has child rosters or active assignments.',
                ]);
            }

            $activeCount = $record->exists ? $record->members()->whereIn('status', $occupying)->count() : 0;
            if ($capacity !== null && $activeCount > $capacity) {
                throw ValidationException::withMessages(['capacity' => 'Roster capacity cannot be lower than its current active assignments.']);
            }

            if ($created) {
                $record->created_by_player_id = $actorPlayerId;
            }
            $record->forceFill([
                'parent_id' => $parent?->id,
                'key' => $key,
                'name_key' => $nameKey === null || trim($nameKey) === '' ? null : trim($nameKey),
                'name' => $name === null || trim($name) === '' ? null : trim($name),
                'roster_type' => $type,
                'assignment_group' => $assignmentGroup,
                'capacity' => $capacity,
                'sort_order' => max(0, $sortOrder),
                'settings' => ['source' => 'manual'] + $settings,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_key' => $key,
                'roster_type' => $type->value,
                'assignment_group' => $assignmentGroup,
                'capacity' => $capacity,
                'actor_player_id' => $actorPlayerId,
            ];
            $eventName = $created ? 'event.roster.created' : 'event.roster.updated';
            $this->audit->record($eventName, $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());
        });
    }

    private function isDescendant(EventRoster $candidateParent, EventRoster $record): bool
    {
        $current = $candidateParent;
        while (true) {
            if ((string) $current->id === (string) $record->id) {
                return true;
            }
            if ($current->parent_id === null) {
                return false;
            }
            $parent = EventRoster::query()->whereKey($current->parent_id)->first();
            if (! $parent instanceof EventRoster) {
                return false;
            }
            $current = $parent;
        }
    }
}
