<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
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
        private EventAuthorization $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $optionIds */
    public function handle(Player $actor, EventPoll $poll, Player $player, array $optionIds): void
    {
        $poll->loadMissing('occurrence.event');
        $event = $poll->occurrence->event;

        DB::transaction(function () use ($actor, $poll, $event, $player, $optionIds): void {
            $context = $this->mutations->requireSelf($actor, $event, $player);
            $this->capabilities->require($context->event, EventCapability::Polls);

            $currentPlayer = $context->actor;
            $occurrence = EventOccurrence::query()
                ->whereKey($poll->occurrence_id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $locked = EventPoll::query()
                ->whereKey($poll->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = CarbonImmutable::now('UTC');
            if ($locked->status !== EventPollStatus::Open
                || ($locked->opens_at !== null && $now->lessThan(CarbonImmutable::instance($locked->opens_at)->utc()))
                || ($locked->closes_at !== null && ! $now->lessThan(CarbonImmutable::instance($locked->closes_at)->utc()))) {
                throw ValidationException::withMessages(['poll' => 'Voting is not currently open.']);
            }

            $ids = array_values(array_unique(array_map('strval', $optionIds)));
            if ($ids === [] || count($ids) > (int) $locked->max_choices) {
                throw ValidationException::withMessages([
                    'options' => 'Select between 1 and '.$locked->max_choices.' option(s).',
                ]);
            }

            $options = EventPollOption::query()
                ->where('poll_id', $locked->id)
                ->whereIn('id', $ids)
                ->get();
            if ($options->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'options' => 'One or more selected poll options are invalid.',
                ]);
            }

            EventPollVote::query()
                ->where('poll_id', $locked->id)
                ->where('player_id', $currentPlayer->id)
                ->delete();
            foreach ($ids as $optionId) {
                EventPollVote::query()->create([
                    'poll_id' => $locked->id,
                    'option_id' => $optionId,
                    'player_id' => $currentPlayer->id,
                    'cast_by_player_id' => $currentPlayer->id,
                    'cast_at' => now(),
                ]);
            }

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'poll_id' => (string) $locked->id,
                'player_id' => (string) $currentPlayer->id,
                'option_ids' => $ids,
            ];
            $this->audit->record('event.poll.vote_cast', $currentPlayer, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.poll.vote_cast',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );
        });
    }
}
