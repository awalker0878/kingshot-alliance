<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Queries;

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingSkill;
use App\Domain\KingPerks\Models\KingPerkPlan;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkScheduleQuery
{
    public function __construct(
        private EventCalendarQuery $events,
        private EventCapabilityGuard $capabilities,
    ) {}

    /** @return array<string, mixed> */
    public function management(Player $actor, string $eventId, ?string $occurrenceId = null): array
    {
        $event = $this->events->eventForManage($actor, $eventId);
        $this->capabilities->require($event, EventCapability::KingPerks);

        if ($event->kingdom_id === null) {
            throw ValidationException::withMessages(['event' => 'King Perks require a Kingdom Event.']);
        }

        $occurrence = $occurrenceId === null
            ? $event->occurrences->sortBy('starts_at')->first()
            : $event->occurrences->firstWhere('id', $occurrenceId);

        if (! $occurrence instanceof EventOccurrence) {
            throw ValidationException::withMessages(['occurrence' => 'No matching Kingdom of Power occurrence was found.']);
        }

        $plan = KingPerkPlan::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['appointments.assignedPlayer', 'positionBlocks', 'skills'])
            ->first();

        return [
            'event' => [
                'id' => (string) $event->id,
                'title' => $event->title,
                'typeSlug' => (string) $event->eventType->slug,
                'kingdomId' => (string) $event->kingdom_id,
                'kingdomName' => $event->kingdom?->name ?? ('Kingdom '.($event->kingdom?->number ?? '')),
            ],
            'occurrence' => [
                'id' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
            ],
            'plan' => $plan === null ? null : $this->plan($plan),
            'players' => Player::query()
                ->where('current_kingdom_id', $event->kingdom_id)
                ->orderBy('current_name')
                ->get(['id', 'current_name'])
                ->map(static fn (Player $player): array => [
                    'id' => (string) $player->id,
                    'name' => (string) $player->current_name,
                ])->all(),
            'appointmentTypes' => array_map(static fn (KingAppointmentType $type): array => [
                'key' => $type->value,
                'label' => $type->label(),
                'durationMinutes' => $type->durationMinutes(),
                'playerCooldownMinutes' => $type->playerCooldownMinutes(),
                'cancelledPositionCooldownMinutes' => $type->cancelledPositionCooldownMinutes(),
                'recommendedFocus' => $type->recommendedFocus(),
            ], KingAppointmentType::cases()),
            'skillTypes' => array_map(static fn (KingSkill $skill): array => [
                'key' => $skill->value,
                'label' => $skill->label(),
                'recommendedFocus' => $skill->recommendedFocus(),
                'advanceSchedulingMinutes' => $skill->advanceSchedulingMinutes(),
            ], KingSkill::cases()),
        ];
    }

    /** @return array<string, mixed> */
    private function plan(KingPerkPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'status' => $plan->status->value,
            'windowStartsAt' => $plan->window_starts_at->toIso8601String(),
            'windowEndsAt' => $plan->window_ends_at->toIso8601String(),
            'publishedAt' => $plan->published_at?->toIso8601String(),
            'appointments' => $plan->appointments->map(static fn ($appointment): array => [
                'id' => (string) $appointment->id,
                'type' => $appointment->appointment_type->value,
                'typeLabel' => $appointment->appointment_type->label(),
                'playerId' => (string) $appointment->assigned_player_id,
                'playerName' => (string) $appointment->assignedPlayer->current_name,
                'startsAt' => $appointment->starts_at->toIso8601String(),
                'endsAt' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status->value,
                'confirmedAt' => $appointment->confirmed_at?->toIso8601String(),
                'notes' => $appointment->notes,
            ])->all(),
            'positionBlocks' => $plan->positionBlocks->map(static fn ($block): array => [
                'id' => (string) $block->id,
                'type' => $block->appointment_type->value,
                'startsAt' => $block->starts_at->toIso8601String(),
                'endsAt' => $block->ends_at->toIso8601String(),
                'reason' => (string) $block->reason,
            ])->all(),
            'skills' => $plan->skills->map(static fn ($skill): array => [
                'id' => (string) $skill->id,
                'key' => $skill->skill_key->value,
                'label' => $skill->skill_key->label(),
                'plannedActivationAt' => $skill->planned_activation_at->toIso8601String(),
                'plannedEndsAt' => $skill->planned_ends_at->toIso8601String(),
                'effectDurationMinutes' => (int) $skill->effect_duration_minutes,
                'scheduleAvailableAt' => CarbonImmutable::instance($skill->planned_activation_at)
                    ->subMinutes($skill->skill_key->advanceSchedulingMinutes())
                    ->toIso8601String(),
                'status' => $skill->status->value,
                'notes' => $skill->notes,
            ])->all(),
        ];
    }
}
