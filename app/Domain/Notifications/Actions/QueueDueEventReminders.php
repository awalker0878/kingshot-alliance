<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventPollStatus;
use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Events\Enums\EventReminderTrigger;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Domain\Notifications\Services\EventReminderAudienceResolver;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueEventReminders
{
    public function __construct(
        private EventReminderAudienceResolver $audiences,
        private EventTargetResolver $targets,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $now = CarbonImmutable::now('UTC');
        $rules = EventReminderRule::query()
            ->where('is_enabled', true)
            ->with([
                'event.occurrences' => static fn ($query) => $query
                    ->where('status', EventOccurrenceStatus::Scheduled->value)
                    ->where('ends_at', '>=', $now)
                    ->orderBy('starts_at'),
                'poll.occurrence.event',
            ])
            ->orderBy('created_at')
            ->limit(max(1, min(1000, $limit)))
            ->get();

        $queued = 0;
        foreach ($rules as $rule) {
            foreach ($this->candidates($rule, $now) as [$occurrence, $dueAt]) {
                if ($dueAt->greaterThan($now)) {
                    continue;
                }

                foreach ($this->audiences->resolve($occurrence, $rule->audience) as $player) {
                    if ($queued >= $limit) {
                        return $queued;
                    }
                    if ($player->user_id === null) {
                        continue;
                    }

                    $created = DB::transaction(function () use ($rule, $occurrence, $player, $dueAt): bool {
                        $key = hash('sha256', implode(':', ['event-reminder', $rule->id, $occurrence->id, $player->id]));
                        $delivery = EventReminderDelivery::query()->firstOrCreate(
                            [
                                'rule_id' => $rule->id,
                                'occurrence_id' => $occurrence->id,
                                'player_id' => $player->id,
                            ],
                            [
                                'recipient_user_id' => $player->user_id,
                                'due_at' => $dueAt,
                                'status' => EventReminderDeliveryStatus::Queued,
                                'attempts' => 0,
                                'idempotency_key' => $key,
                                'queued_at' => now(),
                            ],
                        );

                        if (! $delivery->wasRecentlyCreated) {
                            return false;
                        }

                        $event = $occurrence->event;
                        $target = $this->targets->forEvent($event);
                        $alliance = $target instanceof Alliance ? $target : null;
                        $payload = [
                            'delivery_id' => (string) $delivery->id,
                            'occurrence_id' => (string) $occurrence->id,
                            'event_id' => (string) $event->id,
                            'poll_id' => $rule->poll_id,
                            'trigger_type' => $rule->trigger_type->value,
                            'recipient_user_id' => (int) $player->user_id,
                            'player_id' => (string) $player->id,
                            'channel' => (string) $rule->channel,
                            'due_at' => $dueAt->toIso8601String(),
                        ];
                        $this->outbox->record(
                            'event.reminder.requested',
                            $alliance?->id,
                            $delivery,
                            $payload,
                            idempotencyKey: 'event.reminder.requested:'.$delivery->id,
                            partitionKey: $event->scope->value.':'.$target->id,
                        );

                        return true;
                    });

                    if ($created) {
                        $queued++;
                    }
                }
            }
        }

        return $queued;
    }

    /** @return Collection<int, array{0: EventOccurrence, 1: CarbonImmutable}> */
    private function candidates(EventReminderRule $rule, CarbonImmutable $now): Collection
    {
        if ($rule->trigger_type === EventReminderTrigger::BeforePollClose) {
            $poll = $rule->poll;
            if ($poll === null
                || $poll->status !== EventPollStatus::Open
                || $poll->closes_at === null
                || ! $now->lessThan(CarbonImmutable::instance($poll->closes_at)->utc())
                || $poll->occurrence->status !== EventOccurrenceStatus::Scheduled) {
                return collect();
            }

            return collect([[
                $poll->occurrence,
                CarbonImmutable::instance($poll->closes_at)->utc()->subMinutes((int) $rule->minutes_before),
            ]]);
        }

        return $rule->event->occurrences
            ->map(static fn (EventOccurrence $occurrence): array => [
                $occurrence,
                CarbonImmutable::instance($occurrence->starts_at)->utc()->subMinutes((int) $rule->minutes_before),
            ]);
    }
}
