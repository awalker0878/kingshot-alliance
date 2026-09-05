<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventTargetResolver;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Services\EventReminderAudienceResolver;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueEventReminders
{
    public function __construct(
        private EventReminderAudienceResolver $audiences,
        private EventTargetResolver $targets,
        private PlayerReferenceQuery $players,
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

                    $created = DB::transaction(function () use ($rule, $occurrence, $player, $now): int {
                        $currentEvent = Event::query()
                            ->whereKey($rule->event_id)
                            ->sharedLock()
                            ->first();
                        if (! $currentEvent instanceof Event) {
                            return 0;
                        }

                        $currentOccurrence = EventOccurrence::query()
                            ->whereKey($occurrence->id)
                            ->where('event_id', $currentEvent->id)
                            ->where('status', EventOccurrenceStatus::Scheduled->value)
                            ->sharedLock()
                            ->first();
                        if (! $currentOccurrence instanceof EventOccurrence) {
                            return 0;
                        }

                        $currentPoll = null;
                        if ($rule->poll_id !== null) {
                            $currentPoll = EventPoll::query()
                                ->whereKey($rule->poll_id)
                                ->where('occurrence_id', $currentOccurrence->id)
                                ->sharedLock()
                                ->first();
                            if (! $currentPoll instanceof EventPoll) {
                                return 0;
                            }
                        }

                        $currentRule = EventReminderRule::query()
                            ->whereKey($rule->id)
                            ->where('event_id', $currentEvent->id)
                            ->sharedLock()
                            ->first();
                        if (! $currentRule instanceof EventReminderRule || ! $currentRule->is_enabled) {
                            return 0;
                        }

                        $currentDueAt = $this->dueAt($currentRule, $currentOccurrence, $currentPoll);
                        if ($currentDueAt === null || $currentDueAt->greaterThan($now)) {
                            return 0;
                        }

                        $currentPlayer = $this->players->lockCurrent($player->playerId);
                        if ($currentPlayer->userId === null
                            || ! $this->audiences->includes($currentOccurrence, $currentRule->audience, $currentPlayer)) {
                            return 0;
                        }

                        $receipt = $this->deliveries->queue(NotificationIntent::fromScalars(
                            notificationType: 'event.reminder',
                            recipientUserId: $currentPlayer->userId,
                            playerId: $currentPlayer->playerId,
                            availableAt: $currentDueAt,
                            idempotencyKey: implode(':', [
                                'event-reminder',
                                $currentRule->id,
                                $currentOccurrence->id,
                                $currentPlayer->playerId,
                            ]),
                            title: trim((string) $currentEvent->title) ?: 'Event reminder',
                            body: 'An Alliance event is ready for your attention.',
                            actionUrl: '/events/'.$currentOccurrence->id,
                            subjectType: 'event_occurrence',
                            subjectId: (string) $currentOccurrence->id,
                            metadata: [
                                'event_id' => (string) $currentEvent->id,
                                'rule_id' => (string) $currentRule->id,
                                'poll_id' => $currentRule->poll_id,
                                'trigger_type' => $currentRule->trigger_type->value,
                            ],
                        ));

                        $inAppDeliveryId = $receipt->inAppDeliveryId;
                        if ($inAppDeliveryId !== null
                            && in_array($inAppDeliveryId, $receipt->createdDeliveryIds, true)) {
                            $target = $this->targets->forEvent($currentEvent);
                            $payload = [
                                'delivery_id' => $inAppDeliveryId,
                                'message_id' => $receipt->messageId,
                                'occurrence_id' => (string) $currentOccurrence->id,
                                'event_id' => (string) $currentEvent->id,
                                'poll_id' => $currentRule->poll_id,
                                'trigger_type' => $currentRule->trigger_type->value,
                                'recipient_user_id' => $currentPlayer->userId,
                                'player_id' => $currentPlayer->playerId,
                                'channel' => 'in_app',
                                'due_at' => $currentDueAt->toIso8601String(),
                                'origin' => 'system',
                            ];
                            $this->outbox->record(
                                'event.reminder.requested',
                                $target->allianceId,
                                $currentOccurrence,
                                $payload,
                                idempotencyKey: 'event.reminder.requested:'.$inAppDeliveryId,
                                partitionKey: $target->partitionKey(),
                            );
                        }

                        return count($receipt->createdDeliveryIds);
                    });

                    $queued += $created;
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
