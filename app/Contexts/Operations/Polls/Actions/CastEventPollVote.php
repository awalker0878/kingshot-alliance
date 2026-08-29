<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Actions;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollOption;
use App\Contexts\Operations\Polls\Models\EventPollVote;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CastEventPollVote
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $optionIds */
    public function handle(string $actorPlayerId, string $occurrenceId, string $pollId, array $optionIds): void
    {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $pollId, $optionIds): void {
            $route = EventPoll::query()->select(['id', 'occurrence_id'])->whereKey($pollId)->where('occurrence_id', $occurrenceId)->firstOrFail();
            $occurrenceRoute = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockSelfScope($actorPlayerId, (string) $occurrenceRoute->event_id, $actorPlayerId);
            $this->authorization->authorizeSelf($context, $actorPlayerId);
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceRoute->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $poll = EventPoll::query()->whereKey($pollId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail();

            $now = CarbonImmutable::now('UTC');
            if ($poll->status !== EventPollStatus::Open
                || ($poll->opens_at !== null && $now->lessThan(CarbonImmutable::instance($poll->opens_at)->utc()))
                || ($poll->closes_at !== null && ! $now->lessThan(CarbonImmutable::instance($poll->closes_at)->utc()))) {
                throw ValidationException::withMessages(['poll' => 'Voting is not currently open.']);
            }

            $ids = array_values(array_unique(array_map('strval', $optionIds)));
            if ($ids === [] || count($ids) > (int) $poll->max_choices) {
                throw ValidationException::withMessages(['options' => 'Select between 1 and '.$poll->max_choices.' option(s).']);
            }
            if (EventPollOption::query()->where('poll_id', $poll->id)->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['options' => 'One or more selected poll options are invalid.']);
            }

            EventPollVote::query()->where('poll_id', $poll->id)->where('player_id', $actorPlayerId)->delete();
            foreach ($ids as $optionId) {
                EventPollVote::query()->create([
                    'poll_id' => $poll->id,
                    'option_id' => $optionId,
                    'player_id' => $actorPlayerId,
                    'cast_by_player_id' => $actorPlayerId,
                    'cast_at' => now(),
                ]);
            }

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'poll_id' => (string) $poll->id,
                'player_id' => $actorPlayerId,
                'option_ids' => $ids,
            ];
            $this->audit->record('event.poll.vote_cast', $context->actor, $poll, $context->target->allianceId, $metadata);
            $this->outbox->record('event.poll.vote_cast', $context->target->allianceId, $poll, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
