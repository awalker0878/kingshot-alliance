<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Queries;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\ActivePlayerEventVisibilityResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Notifications\Models\EventReminderDelivery;

final readonly class EventReminderInboxQuery
{
    public function __construct(
        private PlayerContext $playerContext,
        private ActivePlayerEventVisibilityResolver $visibility,
    ) {}

    /** @return list<array<string, mixed>> */
    public function for(User $user, int $limit = 20): array
    {
        $player = $this->playerContext->playerOrNull();
        if (! $player instanceof Player || (int) $player->user_id !== (int) $user->id) {
            return [];
        }

        $targets = $this->visibility->targetIds($player);
        $limit = max(1, min(100, $limit));

        return EventReminderDelivery::query()
            ->where('recipient_user_id', $user->id)
            ->where('player_id', $player->id)
            ->where('status', EventReminderDeliveryStatus::Sent->value)
            ->where('sent_at', '>=', now()->subDays(7))
            ->with(['occurrence.event.eventType'])
            ->orderByDesc('sent_at')
            ->limit($limit * 3)
            ->get()
            ->filter(function (EventReminderDelivery $delivery) use ($targets): bool {
                $event = $delivery->occurrence->event;

                return $event instanceof Event && $this->visibleEventTarget($event, $targets);
            })
            ->take($limit)
            ->map(static function (EventReminderDelivery $delivery): array {
                $occurrence = $delivery->occurrence;
                $event = $occurrence->event;

                return [
                    'id' => (string) $delivery->id,
                    'occurrenceId' => (string) $occurrence->id,
                    'eventTypeSlug' => (string) $event->eventType->slug,
                    'nameKey' => (string) $event->eventType->name_key,
                    'title' => $event->title,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'sentAt' => $delivery->sent_at?->toIso8601String(),
                    'playerId' => (string) $delivery->player_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array{alliance:list<string>,player:list<string>,kingdom:list<string>} $targets
     */
    private function visibleEventTarget(Event $event, array $targets): bool
    {
        return match ($event->scope) {
            EventScope::Player => $event->player_id !== null && in_array((string) $event->player_id, $targets['player'], true),
            EventScope::Alliance => $event->alliance_id !== null && in_array((string) $event->alliance_id, $targets['alliance'], true),
            EventScope::Kingdom => $event->kingdom_id !== null && in_array((string) $event->kingdom_id, $targets['kingdom'], true),
        };
    }
}
