<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Reminders\Actions;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Reminders\Models\EventReminderRule;

final readonly class SyncEventPollDeadlineReminder
{
    public function __construct(
        private CreateEventReminderRule $create,
        private DisableEventReminderRule $disable,
    ) {}

    public function handle(Player $actor, EventPoll $poll): void
    {
        $poll->loadMissing('occurrence.event');
        $event = $poll->occurrence->event;
        $minutes = $poll->settings['deadline_reminder_minutes'] ?? null;
        $desiredMinutes = $poll->status === EventPollStatus::Open
            && $poll->closes_at !== null
            && is_int($minutes)
            && $minutes > 0
                ? $minutes
                : null;

        $rules = EventReminderRule::query()
            ->where('event_id', $event->id)
            ->where('poll_id', $poll->id)
            ->where('trigger_type', EventReminderTrigger::BeforePollClose->value)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->is_enabled && ($desiredMinutes === null || (int) $rule->minutes_before !== $desiredMinutes)) {
                $this->disable->handle($actor, $event, $rule);
            }
        }

        if ($desiredMinutes === null) {
            return;
        }

        $this->create->handle(
            actor: $actor,
            event: $event,
            minutesBefore: $desiredMinutes,
            audience: EventReminderAudience::AllScopePlayers,
            trigger: EventReminderTrigger::BeforePollClose,
            poll: $poll,
        );
    }
}
