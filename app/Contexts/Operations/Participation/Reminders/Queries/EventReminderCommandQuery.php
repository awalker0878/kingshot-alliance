<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Queries;

use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;

final readonly class EventReminderCommandQuery
{
    /**
     * @return array{enabledBeforeStartCount:int,disabledBeforeStartCount:int,channels:list<string>}
     */
    public function forEvent(string $eventId): array
    {
        $rules = EventReminderRule::query()
            ->where('event_id', $eventId)
            ->where('trigger_type', EventReminderTrigger::BeforeStart->value)
            ->get(['is_enabled', 'channel']);

        return [
            'enabledBeforeStartCount' => $rules->where('is_enabled', true)->count(),
            'disabledBeforeStartCount' => $rules->where('is_enabled', false)->count(),
            'channels' => $rules
                ->where('is_enabled', true)
                ->pluck('channel')
                ->map(static fn ($channel): string => (string) $channel)
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
