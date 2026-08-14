<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Enums\EventRosterType;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventRoster
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $settings */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        string $key,
        EventRosterType $type,
        string $assignmentGroup,
        ?string $name = null,
        ?string $nameKey = null,
        ?int $capacity = null,
        int $sortOrder = 0,
        array $settings = [],
        ?EventRoster $parent = null,
        ?EventRoster $roster = null,
    ): EventRoster {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Rosters);
        $this->authorization->authorizeManager($actor, $event);

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
        if ($parent instanceof EventRoster
            && ((string) $parent->occurrence_id !== (string) $occurrence->id
                || (string) $parent->assignment_group !== $assignmentGroup)) {
            throw ValidationException::withMessages(['parent_id' => 'A parent roster must belong to the same occurrence and assignment group.']);
        }
        if ($roster instanceof EventRoster && (string) $roster->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }

        $target = $this->targets->forEvent($event);
        $occupying = array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        );

        return DB::transaction(function () use ($actor, $occurrence, $event, $key, $type, $assignmentGroup, $name, $nameKey, $capacity, $sortOrder, $settings, $parent, $roster, $target,  $occupying): EventRoster {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $record = $roster instanceof EventRoster
                ? EventRoster::query()->whereKey($roster->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventRoster(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;

            if ($parent instanceof EventRoster) {
                $lockedParent = EventRoster::query()->whereKey($parent->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();
                if ($record->exists && $this->isDescendant($lockedParent, $record)) {
                    throw ValidationException::withMessages(['parent_id' => 'A roster cannot be nested beneath itself or one of its descendants.']);
                }
            }

            if ($record->exists && (string) $record->assignment_group !== $assignmentGroup) {
                if ($record->children()->exists() || $record->members()->whereIn('status', $occupying)->exists()) {
                    throw ValidationException::withMessages(['assignment_group' => 'Assignment group cannot change while the roster has child rosters or active assignments.']);
                }
            }

            $activeCount = $record->exists ? $record->members()->whereIn('status', $occupying)->count() : 0;
            if ($capacity !== null && $activeCount > $capacity) {
                throw ValidationException::withMessages(['capacity' => 'Roster capacity cannot be lower than its current active assignments.']);
            }

            if ($created) {
                $record->created_by_player_id = $actor->id;
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
                'updated_by_player_id' => $actor->id,
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_key' => $key,
                'roster_type' => $type->value,
                'assignment_group' => $assignmentGroup,
                'capacity' => $capacity,
                'actor_player_id' => $actor->id,
            ];
            $eventName = $created ? 'event.roster.created' : 'event.roster.updated';
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
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
