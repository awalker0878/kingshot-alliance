<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEventRosterAssignment
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventWorkflowGuard $workflows,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $memberId,
        EventRosterMemberStatus $status,
    ): void {
        if (! in_array($status, [EventRosterMemberStatus::Confirmed, EventRosterMemberStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Roster assignment response must be confirmed or declined.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $memberId, $status): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockSelfScope($actorPlayerId, (string) $route->event_id, $actorPlayerId);
            $this->mutations->authorizeSelf($context, $actorPlayerId);
            $this->workflows->require($context->event, EventWorkflowDimension::Roster);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $member = EventRosterMember::query()->whereKey($memberId)->where('player_id', $actorPlayerId)->lockForUpdate()->firstOrFail();
            $roster = EventRoster::query()->whereKey($member->roster_id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();

            if ((string) $member->player_id !== $actorPlayerId) {
                throw new AuthorizationException;
            }
            if (in_array($member->statusEnum(), [EventRosterMemberStatus::Removed, EventRosterMemberStatus::Participated, EventRosterMemberStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This roster assignment can no longer be confirmed or declined.']);
            }

            if ($status === EventRosterMemberStatus::Confirmed && ! $member->statusEnum()->occupiesSlot()) {
                $occupying = $this->occupyingStatuses();
                $activeCount = EventRosterMember::query()->where('roster_id', $roster->id)->whereIn('status', $occupying)->count();
                if ($roster->capacity !== null && $activeCount >= (int) $roster->capacity) {
                    throw ValidationException::withMessages(['status' => 'This roster is now at capacity.']);
                }
                if ($member->slot_number !== null && EventRosterMember::query()
                    ->where('roster_id', $roster->id)->where('slot_number', $member->slot_number)->whereIn('status', $occupying)
                    ->where('id', '!=', $member->id)->exists()) {
                    throw ValidationException::withMessages(['status' => 'This roster slot has been reassigned.']);
                }
                if (EventRosterMember::query()
                    ->where('player_id', $actorPlayerId)->whereIn('status', $occupying)->where('id', '!=', $member->id)
                    ->whereHas('roster', static fn ($query) => $query
                        ->where('occurrence_id', $occurrence->id)
                        ->where('assignment_group', $roster->assignment_group))->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active assignment in the same roster group.']);
                }

                $this->contexts->freeze($occurrence, $context->actor, $member->alliance_id === null ? null : (string) $member->alliance_id);
            }

            $member->forceFill([
                'status' => $status,
                'responded_by_player_id' => $actorPlayerId,
                'responded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $roster->id,
                'player_id' => $actorPlayerId,
                'status' => $status->value,
            ];
            $this->audit->record('event.roster.assignment_responded', $context->actor, $member, $context->target->allianceId, $metadata);
            $this->outbox->record('event.roster.assignment_responded', $context->target->allianceId, $member, $metadata, partitionKey: $context->target->partitionKey());
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_values(array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        ));
    }
}
