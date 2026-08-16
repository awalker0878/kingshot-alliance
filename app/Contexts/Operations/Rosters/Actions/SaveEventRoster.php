<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventWriteState;
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
        private EventCapabilityGuard $capabilities,
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
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

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

        $occupying = array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        );

        return DB::transaction(function () use ($actor, $occurrence, $event, $key, $type, $assignmentGroup, $name, $nameKey, $capacity, $sortOrder, $settings, $parent, $roster, $occupying): EventRoster {
            $context = $this->eventWriteState->lockEventScope($actor, $event);
            $this->mutations->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::Rosters);

            // The occurrence is the Roster subdomain coordination boundary: hierarchy,
            // assignment-group placement and capacity span multiple roster/member rows.
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $record = $roster instanceof EventRoster
                ? EventRoster::query()
                    ->whereKey($roster->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new EventRoster(['occurrence_id' => $lockedOccurrence->id]);
            $created = ! $record->exists;

            if ($parent instanceof EventRoster) {
                $lockedParent = EventRoster::query()
                    ->whereKey($parent->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($record->exists && $this->isDescendant($lockedParent, $record)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A roster cannot be nested beneath itself or one of its descendants.',
                    ]);
                }
            }

            if ($record->exists && (string) $record->assignment_group !== $assignmentGroup) {
                if ($record->children()->exists() || $record->members()->whereIn('status', $occupying)->exists()) {
                    throw ValidationException::withMessages([
                        'assignment_group' => 'Assignment group cannot change while the roster has child rosters or active assignments.',
                    ]);
                }
            }

            $activeCount = $record->exists ? $record->members()->whereIn('status', $occupying)->count() : 0;
            if ($capacity !== null && $activeCount > $capacity) {
                throw ValidationException::withMessages([
                    'capacity' => 'Roster capacity cannot be lower than its current active assignments.',
                ]);
            }

            if ($created) {
                $record->created_by_player_id = $context->actor->id;
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
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'roster_key' => $key,
                'roster_type' => $type->value,
                'assignment_group' => $assignmentGroup,
                'capacity' => $capacity,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $eventName = $created ? 'event.roster.created' : 'event.roster.updated';
            $this->audit->record($eventName, $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

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
