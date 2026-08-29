<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordEventAttendance
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventParticipantAuthorization $participants,
        private EventWorkflowGuard $workflows,
        private EventPlayerContextFreezer $contexts,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $playerId,
        EventAttendanceStatus $status,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $playerId, $status, $notes): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Participation);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $player = $this->players->lockCurrent($playerId);
            $activeRosterPresence = $context->target->scope === EventScope::Alliance
                && $context->target->allianceId !== null
                && $this->roster->lockActiveRosterPresence($context->target->allianceId, $playerId);

            if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activeRosterPresence)) {
                throw new AuthorizationException;
            }

            $frozenContext = $this->contexts->existing((string) $occurrence->id, $playerId);
            if (! $frozenContext instanceof EventPlayerContext && $status !== EventAttendanceStatus::Unknown) {
                $this->contexts->freeze($occurrence, $player);
            }

            $record = EventAttendance::query()->updateOrCreate(
                ['occurrence_id' => $occurrence->id, 'player_id' => $playerId],
                [
                    'status' => $status,
                    'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                    'recorded_by_player_id' => $actorPlayerId,
                    'recorded_at' => now(),
                ],
            );

            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => $playerId,
                'status' => $status->value,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record('event.attendance.recorded', $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record('event.attendance.recorded', $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
