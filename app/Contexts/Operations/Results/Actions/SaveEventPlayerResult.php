<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

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
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Services\EventMetricCapture;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPlayerResult
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventParticipantAuthorization $participants,
        private EventWorkflowGuard $workflows,
        private EventPlayerContextFreezer $contexts,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private EventMetricCapture $metrics,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<array{key:string,value:int|float|string,dimension_key?:string|null}> $metrics */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $playerId,
        ?string $outcome = null,
        ?int $score = null,
        ?int $rank = null,
        ?string $notes = null,
        array $metrics = [],
        EventMetricSource $metricSource = EventMetricSource::Manual,
    ): void {
        if ($outcome !== null && mb_strlen(trim($outcome)) > 80) {
            throw ValidationException::withMessages(['outcome' => 'Outcome must be 80 characters or fewer.']);
        }
        if ($score !== null && $score < 0) {
            throw ValidationException::withMessages(['score' => 'Score cannot be negative.']);
        }
        if ($rank !== null && $rank < 1) {
            throw ValidationException::withMessages(['rank' => 'Rank must be at least one.']);
        }
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Result notes must be 10000 characters or fewer.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $playerId, $outcome, $score, $rank, $notes, $metrics, $metricSource): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Results);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $player = $context->actor->playerId === $playerId ? $context->actor : $this->players->lockCurrent($playerId);
            $frozen = $this->contexts->existing((string) $occurrence->id, $playerId);
            if (! $frozen instanceof EventPlayerContext) {
                $activeRosterPresence = $context->target->scope === EventScope::Alliance
                    && $context->target->allianceId !== null
                    && $this->roster->lockActiveRosterPresence($context->target->allianceId, $playerId);
                if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activeRosterPresence)) {
                    throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
                }
                $this->contexts->freeze($occurrence, $player);
            }

            $record = EventPlayerResult::query()->where('occurrence_id', $occurrence->id)->where('player_id', $playerId)->lockForUpdate()->first()
                ?? new EventPlayerResult(['occurrence_id' => $occurrence->id, 'player_id' => $playerId]);
            $created = ! $record->exists;
            $record->forceFill([
                'outcome' => $outcome === null || trim($outcome) === '' ? null : trim($outcome),
                'score' => $score,
                'rank' => $rank,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'recorded_by_player_id' => $actorPlayerId,
                'recorded_at' => now(),
            ])->save();
            $this->metrics->forPlayerResult($record, $metrics, $metricSource, $actorPlayerId);

            $eventName = $created ? 'event.player_result.recorded' : 'event.player_result.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => $playerId,
                'player_result_id' => (string) $record->id,
                'score' => $score,
                'rank' => $rank,
                'metric_count' => count($metrics),
                'metric_source' => $metricSource->value,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record($eventName, $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
