<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
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
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private EventMetricCapture $metrics,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<array{key:string,value:int|float|string,dimension_key?:string|null}>  $metrics
     */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        ?string $outcome = null,
        ?int $score = null,
        ?int $opponentScore = null,
        ?int $rank = null,
        ?string $notes = null,
        array $metrics = [],
        EventMetricSource $metricSource = EventMetricSource::Manual,
    ): EventResult {
        $event = $occurrence->event()->firstOrFail();
        $this->validate($outcome, $score, $opponentScore, $rank, $notes);

        return DB::transaction(function () use ($actor, $occurrence, $event, $outcome, $score, $opponentScore, $rank, $notes, $metrics, $metricSource): EventResult {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Results);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $record = EventResult::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->lockForUpdate()
                ->first()
                ?? new EventResult(['occurrence_id' => $lockedOccurrence->id]);
            $created = ! $record->exists;

            $record->forceFill([
                'outcome' => $outcome === null || trim($outcome) === '' ? null : trim($outcome),
                'score' => $score,
                'opponent_score' => $opponentScore,
                'rank' => $rank,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'recorded_by_player_id' => $context->actor->id,
                'recorded_at' => now(),
            ])->save();

            $this->metrics->forEventResult($record, $metrics, $metricSource, $context->actor);

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $eventName = $created ? 'event.result.recorded' : 'event.result.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'result_id' => (string) $record->id,
                'score' => $score,
                'opponent_score' => $opponentScore,
                'rank' => $rank,
                'metric_count' => count($metrics),
                'metric_source' => $metricSource->value,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

            return $record->refresh()->load('metrics.definition');
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
