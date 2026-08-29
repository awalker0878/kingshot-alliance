<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Contexts\Operations\Results\Models\EventResult;
use App\Contexts\Operations\Results\Services\EventMetricCapture;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventResult
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventWorkflowGuard $workflows,
        private EventMetricCapture $metrics,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<array{key:string,value:int|float|string,dimension_key?:string|null}> $metrics */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        ?string $outcome = null,
        ?int $score = null,
        ?int $opponentScore = null,
        ?int $rank = null,
        ?string $notes = null,
        array $metrics = [],
        EventMetricSource $metricSource = EventMetricSource::Manual,
    ): void {
        $this->validate($outcome, $score, $opponentScore, $rank, $notes);

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $outcome, $score, $opponentScore, $rank, $notes, $metrics, $metricSource): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Results);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $record = EventResult::query()->where('occurrence_id', $occurrence->id)->lockForUpdate()->first()
                ?? new EventResult(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;
            $record->forceFill([
                'outcome' => $outcome === null || trim($outcome) === '' ? null : trim($outcome),
                'score' => $score,
                'opponent_score' => $opponentScore,
                'rank' => $rank,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'recorded_by_player_id' => $actorPlayerId,
                'recorded_at' => now(),
            ])->save();

            $this->metrics->forEventResult($record, $metrics, $metricSource, $actorPlayerId);
            $eventName = $created ? 'event.result.recorded' : 'event.result.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'event_result_id' => (string) $record->id,
                'score' => $score,
                'opponent_score' => $opponentScore,
                'rank' => $rank,
                'metric_count' => count($metrics),
                'metric_source' => $metricSource->value,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record($eventName, $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());
        });
    }

    private function validate(?string $outcome, ?int $score, ?int $opponentScore, ?int $rank, ?string $notes): void
    {
        if ($outcome !== null && mb_strlen(trim($outcome)) > 80) {
            throw ValidationException::withMessages(['outcome' => 'Outcome must be 80 characters or fewer.']);
        }
        if ($score !== null && $score < 0) {
            throw ValidationException::withMessages(['score' => 'Score cannot be negative.']);
        }
        if ($opponentScore !== null && $opponentScore < 0) {
            throw ValidationException::withMessages(['opponent_score' => 'Opponent score cannot be negative.']);
        }
        if ($rank !== null && $rank < 1) {
            throw ValidationException::withMessages(['rank' => 'Rank must be at least one.']);
        }
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) {
            throw ValidationException::withMessages(['notes' => 'Result notes must be 10000 characters or fewer.']);
        }
    }
}
