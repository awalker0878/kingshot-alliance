<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPlayerResult;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPlayerResult
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        Player $player,
        ?string $outcome = null,
        ?int $score = null,
        ?int $rank = null,
        ?string $notes = null,
    ): EventPlayerResult {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

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

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $outcome, $score, $rank, $notes): EventPlayerResult {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Results);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $currentPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->participants->eligible($context->event, $currentPlayer)) {
                throw ValidationException::withMessages([
                    'player' => 'This Player is not eligible for the Event target.',
                ]);
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

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $eventName = $created ? 'event.player_result.recorded' : 'event.player_result.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'player_result_id' => (string) $record->id,
                'score' => $score,
                'rank' => $rank,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $record->refresh()->load('player');
        });
    }
}
