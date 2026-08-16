<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Reminders\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventTargetResolver;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Reminders\Services\EventReminderAudienceResolver;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueEventReminders
{
    public function __construct(
        private EventReminderAudienceResolver $audiences,
        private EventTargetResolver $targets,
        private NotificationDeliveryService $deliveries,
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

                    $created = DB::transaction(function () use ($rule, $occurrence, $player, $now): bool {
                        $currentEvent = Event::query()
                            ->whereKey($rule->event_id)
                            ->sharedLock()
                            ->first();
                        if (! $currentEvent instanceof Event) {
                            return false;
                        }

                        $currentOccurrence = EventOccurrence::query()
                            ->whereKey($occurrence->id)
                            ->where('event_id', $currentEvent->id)
                            ->where('status', EventOccurrenceStatus::Scheduled->value)
                            ->sharedLock()
                            ->first();
                        if (! $currentOccurrence instanceof EventOccurrence) {
                            return false;
                        }

                        $currentPoll = null;
                        if ($rule->poll_id !== null) {
                            $currentPoll = EventPoll::query()
                                ->whereKey($rule->poll_id)
                                ->where('occurrence_id', $currentOccurrence->id)
                                ->sharedLock()
                                ->first();
                            if (! $currentPoll instanceof EventPoll) {
                                return false;
                            }
                        }

                        $currentRule = EventReminderRule::query()
                            ->whereKey($rule->id)
                            ->where('event_id', $currentEvent->id)
                            ->sharedLock()
                            ->first();
                        if (! $currentRule instanceof EventReminderRule || ! $currentRule->is_enabled) {
                            return false;
                        }

                        $currentDueAt = $this->dueAt($currentRule, $currentOccurrence, $currentPoll);
                        if ($currentDueAt === null || $currentDueAt->greaterThan($now)) {
                            return false;
                        }

                        $currentPlayer = Player::query()
                            ->whereKey($player->id)
                            ->whereNotNull('user_id')
                            ->sharedLock()
                            ->first();
                        if (! $currentPlayer instanceof Player
                            || ! $this->audiences->includes($currentOccurrence, $currentRule->audience, $currentPlayer)) {
                            return false;
                        }

                        $notificationType = 'event.reminder';
                        $channel = (string) $currentRule->channel;
                        if (! $this->deliveries->isEnabled(
                            (int) $currentPlayer->user_id,
                            (string) $currentPlayer->id,
                            $notificationType,
                            $channel,
                        )) {
                            return false;
                        }

                        $key = hash('sha256', implode(':', [
                            'event-reminder',
                            $currentRule->id,
                            $currentOccurrence->id,
                            $currentPlayer->id,
                        ]));
                        $delivery = $this->deliveries->queue(
                            notificationType: $notificationType,
                            recipientUserId: (int) $currentPlayer->user_id,
                            playerId: (string) $currentPlayer->id,
                            channel: $channel,
                            dueAt: $currentDueAt,
                            idempotencyKey: $key,
                            subjectType: 'event_occurrence',
                            subjectId: (string) $currentOccurrence->id,
                            metadata: [
                                'event_id' => (string) $currentEvent->id,
                                'rule_id' => (string) $currentRule->id,
                                'poll_id' => $currentRule->poll_id,
                                'trigger_type' => $currentRule->trigger_type->value,
                            ],
                        );

                        if (! $delivery->wasRecentlyCreated) {
                            return false;
                        }

                        $target = $this->targets->forEvent($currentEvent);
                        $alliance = $target instanceof Alliance ? $target : null;
                        $payload = [
                            'delivery_id' => (string) $delivery->id,
                            'occurrence_id' => (string) $currentOccurrence->id,
                            'event_id' => (string) $currentEvent->id,
                            'poll_id' => $currentRule->poll_id,
                            'trigger_type' => $currentRule->trigger_type->value,
                            'recipient_user_id' => (int) $currentPlayer->user_id,
                            'player_id' => (string) $currentPlayer->id,
                            'channel' => $channel,
                            'due_at' => $currentDueAt->toIso8601String(),
                            'origin' => 'system',
                        ];
                        $this->outbox->record(
                            'event.reminder.requested',
                            $alliance?->id,
                            $delivery,
                            $payload,
                            idempotencyKey: 'event.reminder.requested:'.$delivery->id,
                            partitionKey: $currentEvent->scope->value.':'.$target->id,
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

    private function dueAt(EventReminderRule $rule, EventOccurrence $occurrence, ?EventPoll $poll): ?CarbonImmutable
    {
        if ($rule->trigger_type === EventReminderTrigger::BeforePollClose) {
            if (! $poll instanceof EventPoll
                || $poll->status !== EventPollStatus::Open
                || $poll->closes_at === null) {
                return null;
            }

            return CarbonImmutable::instance($poll->closes_at)->utc()->subMinutes((int) $rule->minutes_before);
        }

        return CarbonImmutable::instance($occurrence->starts_at)->utc()->subMinutes((int) $rule->minutes_before);
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
