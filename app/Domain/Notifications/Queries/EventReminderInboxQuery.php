<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Queries;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\ActivePlayerEventVisibilityResolver;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Domain\KingPerks\Enums\KingPerkReminderKind;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingSkillPlan;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Notifications\Models\KingPerkReminderDelivery;

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
        $items = array_merge(
            $this->eventReminders($user, $player, $targets, $limit),
            $this->kingPerkReminders($user, $player, $targets, $limit),
        );

        usort($items, static fn (array $left, array $right): int => strcmp(
            (string) ($right['sentAt'] ?? ''),
            (string) ($left['sentAt'] ?? ''),
        ));

        return array_values(array_slice($items, 0, $limit));
    }

    /**
     * @param  array{alliance:list<string>,player:list<string>,kingdom:list<string>}  $targets
     * @return list<array<string, mixed>>
     */
    private function eventReminders(User $user, Player $player, array $targets, int $limit): array
    {
        $items = [];
        $deliveries = EventReminderDelivery::query()
            ->where('recipient_user_id', $user->id)
            ->where('player_id', $player->id)
            ->where('status', EventReminderDeliveryStatus::Sent->value)
            ->where('sent_at', '>=', now()->subDays(7))
            ->with(['occurrence.event.eventType'])
            ->orderByDesc('sent_at')
            ->limit($limit * 3)
            ->get();

        foreach ($deliveries as $delivery) {
            $occurrence = $delivery->occurrence;
            if (! $occurrence instanceof EventOccurrence) {
                continue;
            }

            $event = $occurrence->event;
            if (! $event instanceof Event || ! $this->visibleEventTarget($event, $targets)) {
                continue;
            }

            $eventType = $event->eventType;
            if (! $eventType instanceof EventType) {
                continue;
            }

            $items[] = [
                'id' => (string) $delivery->id,
                'occurrenceId' => (string) $occurrence->id,
                'eventTypeSlug' => (string) $eventType->slug,
                'nameKey' => (string) $eventType->name_key,
                'title' => $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'sentAt' => $delivery->sent_at?->toIso8601String(),
                'playerId' => (string) $delivery->player_id,
            ];
        }

        return $items;
    }

    /**
     * @param  array{alliance:list<string>,player:list<string>,kingdom:list<string>}  $targets
     * @return list<array<string, mixed>>
     */
    private function kingPerkReminders(User $user, Player $player, array $targets, int $limit): array
    {
        $items = [];
        $deliveries = KingPerkReminderDelivery::query()
            ->where('recipient_user_id', $user->id)
            ->where('player_id', $player->id)
            ->where('status', EventReminderDeliveryStatus::Sent->value)
            ->where('sent_at', '>=', now()->subDays(7))
            ->with([
                'plan.occurrence.event.eventType',
                'appointment',
                'skillPlan',
            ])
            ->orderByDesc('sent_at')
            ->limit($limit * 3)
            ->get();

        foreach ($deliveries as $delivery) {
            $plan = $delivery->plan;
            if (! $plan instanceof KingPerkPlan) {
                continue;
            }

            $occurrence = $plan->occurrence;
            if (! $occurrence instanceof EventOccurrence) {
                continue;
            }

            $event = $occurrence->event;
            if (! $event instanceof Event || ! $this->visibleEventTarget($event, $targets)) {
                continue;
            }

            $eventType = $event->eventType;
            if (! $eventType instanceof EventType) {
                continue;
            }

            $appointment = $delivery->appointment;
            $skill = $delivery->skillPlan;
            $startsAt = $appointment instanceof KingPerkAppointment
                ? $appointment->starts_at
                : ($skill instanceof KingSkillPlan ? $skill->planned_activation_at : $occurrence->starts_at);

            $title = match ($delivery->kind) {
                KingPerkReminderKind::AppointmentUnconfirmed10Minutes => ($appointment instanceof KingPerkAppointment ? $appointment->appointment_type->label() : 'King appointment').' · confirmation needed',
                KingPerkReminderKind::Appointment24Hours,
                KingPerkReminderKind::Appointment1Hour,
                KingPerkReminderKind::Appointment10Minutes => ($appointment instanceof KingPerkAppointment ? $appointment->appointment_type->label() : 'King appointment').' · appointment reminder',
                KingPerkReminderKind::SkillSchedulingAvailable => ($skill instanceof KingSkillPlan ? $skill->skill_key->label() : 'King Skill').' · ready to schedule in game',
                KingPerkReminderKind::Skill1Hour => ($skill instanceof KingSkillPlan ? $skill->skill_key->label() : 'King Skill').' · activation reminder',
            };

            $items[] = [
                'id' => 'king-perk:'.$delivery->id,
                'occurrenceId' => (string) $occurrence->id,
                'eventTypeSlug' => (string) $eventType->slug,
                'nameKey' => (string) $eventType->name_key,
                'title' => $title,
                'startsAt' => $startsAt->toIso8601String(),
                'sentAt' => $delivery->sent_at?->toIso8601String(),
                'playerId' => (string) $delivery->player_id,
            ];
        }

        return $items;
    }

    /**
     * @param  array{alliance:list<string>,player:list<string>,kingdom:list<string>}  $targets
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
