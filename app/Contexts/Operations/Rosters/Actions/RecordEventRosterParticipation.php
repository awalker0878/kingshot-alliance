<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordEventRosterParticipation
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventParticipantAuthorization $participants,
        private EventWorkflowGuard $workflows,
        private EventPlayerContextFreezer $contexts,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $rosterEntries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $memberId,
        EventRosterMemberStatus $status,
    ): void {
        if (! in_array($status, [EventRosterMemberStatus::Participated, EventRosterMemberStatus::Absent], true)) {
            throw ValidationException::withMessages(['status' => 'Roster participation must be recorded as participated or absent.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $memberId, $status): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Roster);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $member = EventRosterMember::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();
            $roster = EventRoster::query()->whereKey($member->roster_id)->where('occurrence_id', $occurrence->id)->sharedLock()->firstOrFail();

            if (in_array($member->statusEnum(), [EventRosterMemberStatus::Declined, EventRosterMemberStatus::Removed], true)) {
                throw ValidationException::withMessages(['status' => 'Declined or removed roster assignments cannot receive participation.']);
            }

            $playerId = (string) $member->player_id;
            $player = $context->actor->playerId === $playerId ? $context->actor : $this->players->lockCurrent($playerId);
            $frozen = $this->contexts->existing((string) $occurrence->id, $playerId);
            if (! $frozen instanceof EventPlayerContext) {
                $activeRosterPresence = $context->target->scope === EventScope::Alliance
                    && $context->target->allianceId !== null
                    && $this->rosterEntries->lockActiveRosterPresence($context->target->allianceId, $playerId);
                if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activeRosterPresence)) {
                    throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
                }
                $this->contexts->freeze($occurrence, $player, $member->alliance_id === null ? null : (string) $member->alliance_id);
            }

            $member->forceFill(['status' => $status])->save();
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $roster->id,
                'player_id' => $playerId,
                'status' => $status->value,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record('event.roster.participation.recorded', $context->actor, $member, $context->target->allianceId, $metadata);
            $this->outbox->record('event.roster.participation.recorded', $context->target->allianceId, $member, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
