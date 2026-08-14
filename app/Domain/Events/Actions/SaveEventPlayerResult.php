<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPlayerResult;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPlayerResult
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $metrics */
    public function handle(Player $actor, EventOccurrence $occurrence, Player $player, ?string $outcome = null, ?int $score = null, ?int $rank = null, array $metrics = [], ?string $notes = null): EventPlayerResult
    {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Results);
        $this->authorization->authorizeManager($actor, $event);
        if (! $this->authorization->eligible($event, $player)) {
            throw ValidationException::withMessages(['player' => 'This Player is not eligible for the Event target.']);
        }
        if ($outcome !== null && mb_strlen(trim($outcome)) > 80) throw ValidationException::withMessages(['outcome' => 'Outcome must be 80 characters or fewer.']);
        if ($score !== null && $score < 0) throw ValidationException::withMessages(['score' => 'Score cannot be negative.']);
        if ($rank !== null && $rank < 1) throw ValidationException::withMessages(['rank' => 'Rank must be at least one.']);
        if ($notes !== null && mb_strlen(trim($notes)) > 10000) throw ValidationException::withMessages(['notes' => 'Result notes must be 10000 characters or fewer.']);

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $outcome, $score, $rank, $metrics, $notes, $target): EventPlayerResult {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $record = EventPlayerResult::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->lockForUpdate()->first()
                ?? new EventPlayerResult(['occurrence_id' => $occurrence->id, 'player_id' => $player->id]);
            $created = ! $record->exists;
            $record->forceFill([
                'outcome' => $outcome === null || trim($outcome) === '' ? null : trim($outcome),
                'score' => $score,
                'rank' => $rank,
                'metrics' => $metrics === [] ? null : $metrics,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'recorded_by_player_id' => $actor->id,
                'recorded_at' => now(),
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $eventName = $created ? 'event.player_result.recorded' : 'event.player_result.updated';
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => (string) $player->id,
                'player_result_id' => (string) $record->id,
                'score' => $score,
                'rank' => $rank,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh()->load('player');
        });
    }
}
