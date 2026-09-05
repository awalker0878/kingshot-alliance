<?php

declare(strict_types=1);

namespace App\ReadModels\EventCalendar\Queries;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Services\ActivePlayerEventVisibilityResolver;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;

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
        if (! ($player instanceof PlayerReference) || $player->userId !== (int) $user->id) {
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
    private function eventReminders(User $user, PlayerReference $player, array $targets, int $limit): array
    {
        $items = [];
        $messages = NotificationMessage::query()
            ->where('notification_type', 'event.reminder')
            ->where('subject_type', 'event_occurrence')
            ->where('recipient_user_id', $user->id)
            ->where('player_id', $player->playerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit($limit * 3)
            ->get();

        foreach ($messages as $message) {
            $occurrence = EventOccurrence::query()
                ->with('event.eventType')
                ->whereKey($message->subject_id)
                ->first();
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
                'id' => (string) $message->id,
                'occurrenceId' => (string) $occurrence->id,
                'eventTypeSlug' => (string) $eventType->slug,
                'nameKey' => (string) $eventType->name_key,
                'title' => $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'sentAt' => $message->created_at?->toIso8601String(),
                'playerId' => (string) $message->player_id,
            ];
        }

        return $items;
    }

    /**
     * @param  array{alliance:list<string>,player:list<string>,kingdom:list<string>}  $targets
     * @return list<array<string, mixed>>
     */
    private function kingPerkReminders(User $user, PlayerReference $player, array $targets, int $limit): array
    {
        $items = [];
        $messages = NotificationMessage::query()
            ->where('notification_type', 'king_perks.reminder')
            ->whereIn('subject_type', ['king_perk_appointment', 'king_skill_plan'])
            ->where('recipient_user_id', $user->id)
            ->where('player_id', $player->playerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit($limit * 3)
            ->get();

        foreach ($messages as $message) {
            [$appointment, $skill, $plan] = $this->kingPerkSubject($message);
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

            $metadata = is_array($message->metadata) ? $message->metadata : [];
            $kindValue = $metadata['kind'] ?? null;
            $kind = is_string($kindValue) ? KingPerkReminderKind::tryFrom($kindValue) : null;
            if (! $kind instanceof KingPerkReminderKind) {
                continue;
            }

            $startsAt = $appointment instanceof KingPerkAppointment
                ? $appointment->starts_at
                : ($skill instanceof KingSkillPlan ? $skill->planned_activation_at : $occurrence->starts_at);

            $title = match ($kind) {
                KingPerkReminderKind::AppointmentUnconfirmed10Minutes => ($appointment instanceof KingPerkAppointment ? $appointment->appointment_type->label() : 'King appointment').' · confirmation needed',
                KingPerkReminderKind::Appointment24Hours,
                KingPerkReminderKind::Appointment1Hour,
                KingPerkReminderKind::Appointment10Minutes => ($appointment instanceof KingPerkAppointment ? $appointment->appointment_type->label() : 'King appointment').' · appointment reminder',
                KingPerkReminderKind::SkillSchedulingAvailable => ($skill instanceof KingSkillPlan ? $skill->skill_key->label() : 'King Skill').' · ready to schedule in game',
                KingPerkReminderKind::Skill1Hour => ($skill instanceof KingSkillPlan ? $skill->skill_key->label() : 'King Skill').' · activation reminder',
            };

            $items[] = [
                'id' => 'king-perk:'.$message->id,
                'occurrenceId' => (string) $occurrence->id,
                'eventTypeSlug' => (string) $eventType->slug,
                'nameKey' => (string) $eventType->name_key,
                'title' => $title,
                'startsAt' => $startsAt->toIso8601String(),
                'sentAt' => $message->created_at?->toIso8601String(),
                'playerId' => (string) $message->player_id,
            ];
        }

        return $items;
    }

    /** @return array{0:?KingPerkAppointment,1:?KingSkillPlan,2:?KingPerkPlan} */
    private function kingPerkSubject(NotificationMessage $message): array
    {
        if ($message->subject_type === 'king_perk_appointment') {
            $appointment = KingPerkAppointment::query()
                ->with('plan.occurrence.event.eventType')
                ->whereKey($message->subject_id)
                ->first();

            return [
                $appointment instanceof KingPerkAppointment ? $appointment : null,
                null,
                $appointment instanceof KingPerkAppointment && $appointment->plan instanceof KingPerkPlan
                    ? $appointment->plan
                    : null,
            ];
        }

        if ($message->subject_type === 'king_skill_plan') {
            $skill = KingSkillPlan::query()
                ->with('plan.occurrence.event.eventType')
                ->whereKey($message->subject_id)
                ->first();

            return [
                null,
                $skill instanceof KingSkillPlan ? $skill : null,
                $skill instanceof KingSkillPlan && $skill->plan instanceof KingPerkPlan
                    ? $skill->plan
                    : null,
            ];
        }

        return [null, null, null];
    }

    /** @param array{alliance:list<string>,player:list<string>,kingdom:list<string>} $targets */
    private function visibleEventTarget(Event $event, array $targets): bool
    {
        return match ($event->scope) {
            EventScope::Player => $event->player_id !== null && in_array((string) $event->player_id, $targets['player'], true),
            EventScope::Alliance => $event->alliance_id !== null && in_array((string) $event->alliance_id, $targets['alliance'], true),
            EventScope::Kingdom => $event->kingdom_id !== null && in_array((string) $event->kingdom_id, $targets['kingdom'], true),
        };
    }
}
