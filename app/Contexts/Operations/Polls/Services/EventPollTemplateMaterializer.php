<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Services;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventCapabilityResolver;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use App\Contexts\Operations\Polls\Models\EventPoll;
use Carbon\CarbonImmutable;

final readonly class EventPollTemplateMaterializer
{
    public function __construct(private EventCapabilityResolver $capabilities) {}

    public function materializeDefaults(EventOccurrence $occurrence, Player $actor): void
    {
        $occurrence->loadMissing('event.typeScope.capabilities');
        $event = $occurrence->event;
        if (! $this->capabilities->supports($event->typeScope, EventCapability::Polls)) {
            return;
        }

        $configuration = $this->capabilities->configuration($event->typeScope, EventCapability::Polls);
        $definitions = $configuration['default_polls'] ?? [];
        if (! is_array($definitions)) {
            return;
        }

        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! isset($definition['key'], $definition['poll_type'])) {
                continue;
            }

            $key = (string) $definition['key'];
            if (EventPoll::query()->where('occurrence_id', $occurrence->id)->where('key', $key)->exists()) {
                continue;
            }

            $opensAt = $this->relativeTime($occurrence, $definition['opens_offset_minutes'] ?? null);
            $closesAt = $this->relativeTime($occurrence, $definition['closes_offset_minutes'] ?? null);
            EventPoll::query()->create([
                'occurrence_id' => $occurrence->id,
                'key' => $key,
                'poll_type' => EventPollType::from((string) $definition['poll_type']),
                'question_key' => isset($definition['question_key']) ? (string) $definition['question_key'] : null,
                'question' => isset($definition['question']) ? (string) $definition['question'] : null,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'status' => EventPollStatus::Draft,
                'max_choices' => max(1, (int) ($definition['max_choices'] ?? 1)),
                'settings' => [
                    'source' => 'catalogue',
                    'manager_supplied_options' => (bool) ($definition['manager_supplied_options'] ?? false),
                    'deadline_reminder_minutes' => isset($definition['deadline_reminder_minutes']) ? (int) $definition['deadline_reminder_minutes'] : null,
                ],
                'created_by_player_id' => $actor->id,
                'updated_by_player_id' => $actor->id,
            ]);
        }
    }

    private function relativeTime(EventOccurrence $occurrence, mixed $offset): ?CarbonImmutable
    {
        if (! is_int($offset) && ! is_numeric($offset)) {
            return null;
        }

        return CarbonImmutable::instance($occurrence->starts_at)->utc()->addMinutes((int) $offset);
    }
}
