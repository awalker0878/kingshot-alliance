<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Queries;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Services\EventReminderAudienceResolver;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;

final readonly class EventReminderNotificationEligibilityQuery
{
    public function __construct(private EventReminderAudienceResolver $audience) {}

    public function allows(NotificationSource $source, PlayerReference $player): bool
    {
        $ruleId = $source->metadataString('rule_id');
        if ($source->subjectType !== 'event_occurrence' || $source->subjectId === null || $ruleId === null) {
            return false;
        }
        $occurrence = EventOccurrence::query()->whereKey($source->subjectId)
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereHas('event', static fn ($query) => $query->where('status', EventStatus::Published->value))->first();
        if (! $occurrence instanceof EventOccurrence || (string) $occurrence->event_id !== $source->metadataString('event_id')) {
            return false;
        }
        $rule = EventReminderRule::query()->whereKey($ruleId)->where('event_id', $occurrence->event_id)
            ->where('is_enabled', true)->first();
        if (! $rule instanceof EventReminderRule) {
            return false;
        }
        if ($rule->trigger_type === EventReminderTrigger::BeforePollClose
            && ! EventPoll::query()->whereKey($rule->poll_id)->where('occurrence_id', $occurrence->id)
                ->where('status', EventPollStatus::Open->value)->where('closes_at', '>', now())->exists()) {
            return false;
        }

        return $this->audience->includes($occurrence, $rule->audience, $player);
    }
}
