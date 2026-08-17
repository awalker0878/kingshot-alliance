<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;

final readonly class SyncEventPollDeadlineReminder
{
    public function __construct(
        private CreateEventReminderRule $create,
        private DisableEventReminderRule $disable,
    ) {}

    public function handle(string $actorPlayerId, string $pollId): void
    {
        $poll = EventPoll::query()->whereKey($pollId)->with('occurrence:id,event_id')->firstOrFail();
        $eventId = (string) $poll->occurrence->event_id;
        $minutes = $poll->settings['deadline_reminder_minutes'] ?? null;
        $desiredMinutes = $poll->status === EventPollStatus::Open
            && $poll->closes_at !== null
            && is_int($minutes)
            && $minutes > 0
                ? $minutes
                : null;

        $rules = EventReminderRule::query()
            ->where('event_id', $eventId)
            ->where('poll_id', $pollId)
            ->where('trigger_type', EventReminderTrigger::BeforePollClose->value)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->is_enabled && ($desiredMinutes === null || (int) $rule->minutes_before !== $desiredMinutes)) {
                $this->disable->handle($actorPlayerId, $eventId, (string) $rule->id);
            }
        }

        if ($desiredMinutes === null) {
            return;
        }

        $this->create->handle(
            actorPlayerId: $actorPlayerId,
            eventId: $eventId,
            minutesBefore: $desiredMinutes,
            audience: EventReminderAudience::AllScopePlayers,
            trigger: EventReminderTrigger::BeforePollClose,
            pollId: $pollId,
        );
    }
}
