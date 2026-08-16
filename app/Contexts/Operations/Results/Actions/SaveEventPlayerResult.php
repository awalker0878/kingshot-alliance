<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

use App\Contexts\Operations\EventCore\Services\EventWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
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
        private EventCapabilityGuard $capabilities,
        private EventPlayerContextFreezer $contexts,
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
        Player $player,
        ?string $outcome = null,
        ?int $score = null,
        ?int $rank = null,
        ?string $notes = null,
        array $metrics = [],
        EventMetricSource $metricSource = EventMetricSource::Manual,
    ): EventPlayerResult {
        $event = $occurrence->event()->firstOrFail();

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

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $outcome, $score, $rank, $notes, $metrics, $metricSource): EventPlayerResult {
            $context = $this->eventWriteState->lockEventScope($actor, $event);
            $this->mutations->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::Results);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentPlayer = (string) $context->actor->id === (string) $player->id
                ? $context->actor
                : Player::query()->whereKey($player->id)->firstOrFail();
            $frozenContext = $this->contexts->existing($lockedOccurrence, $currentPlayer);

            if (! ($frozenContext instanceof EventPlayerContext)) {
                if ((string) $context->actor->id !== (string) $currentPlayer->id) {
                    $currentPlayer = Player::query()
                        ->whereKey($currentPlayer->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                if (! $this->participants->eligible($context->event, $currentPlayer)) {
                    throw ValidationException::withMessages([
                        'player' => 'This Player is not eligible for the Event target.',
                    ]);
                }

                $this->contexts->freeze($lockedOccurrence, $currentPlayer);
            }

            $record = EventPlayerResult::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->where('player_id', $currentPlayer->id)
                ->lockForUpdate()
                ->first()
                ?? new EventPlayerResult([
                    'occurrence_id' => $lockedOccurrence->id,
                    'player_id' => $currentPlayer->id,
                ]);
            $created = ! $record->exists;

            $record->forceFill([
                'outcome' => $outcome === null || trim($outcome) === '' ? null : trim($outcome),
                'score' => $score,
                'rank' => $rank,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'recorded_by_player_id' => $context->actor->id,
                'recorded_at' => now(),
            ])->save();

            $this->metrics->forPlayerResult($record, $metrics, $metricSource, $context->actor);

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $eventName = $created ? 'event.player_result.recorded' : 'event.player_result.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'player_result_id' => (string) $record->id,
                'score' => $score,
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

            return $record->refresh()->load(['player', 'metrics.definition']);
        });
    }
}
