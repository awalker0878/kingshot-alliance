<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Queries;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingPerkPositionBlock;
use App\Contexts\Operations\KingPerks\Models\KingPerkRequest;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkPreparationPresetCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkScheduleQuery
{
    public function __construct(
        private KingdomReferenceQuery $kingdoms,
        private PlayerReferenceQuery $players,
        private EventCalendarQuery $events,
        private KingPerkPreparationPresetCatalog $presets,
    ) {}

    /** @phpstan-return array<string, mixed> */
    public function management(PlayerReference $actor, string $eventId, ?string $occurrenceId = null): array
    {
        $event = $this->events->eventForManage($actor, $eventId);
        $occurrence = $this->selectOccurrence($event, $occurrenceId);
        $plan = $this->loadPlan($occurrence, management: true);

        return [
            ...$this->eventPayload($event, $occurrence),
            'plan' => $plan === null ? null : $this->plan($plan),
            'live' => $plan === null ? null : $this->live($plan),
            'strategyDays' => $plan === null ? [] : $this->presets->forWindow(
                CarbonImmutable::instance($plan->window_starts_at),
                CarbonImmutable::instance($plan->window_ends_at),
            ),
            'players' => array_map(static fn (PlayerReference $player): array => [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ], $event->kingdom_id === null ? [] : $this->players->inKingdom((string) $event->kingdom_id)),
            'appointmentTypes' => $this->appointmentTypes(),
            'pushCategories' => $this->pushCategories(),
            'skillTypes' => $this->skillTypes(),
        ];
    }

    /** @phpstan-return array<string, mixed> */
    public function player(PlayerReference $actor, string $eventId, ?string $occurrenceId = null): array
    {
        $route = Event::query()->whereKey($eventId)->with('occurrences')->firstOrFail();
        $occurrence = $this->selectOccurrence($route, $occurrenceId);
        $authorized = $this->events->occurrence($actor, (string) $occurrence->id);
        $event = $authorized->event;
        $plan = KingPerkPlan::query()
            ->where('occurrence_id', $authorized->id)
            ->whereIn('status', [KingPerkPlanStatus::Published->value, KingPerkPlanStatus::Active->value])
            ->with([
                'appointments' => static fn ($query) => $query
                    ->where('assigned_player_id', $actor->playerId)
                    ->orderBy('starts_at'),
                'requests' => static fn ($query) => $query
                    ->where('player_id', $actor->playerId)
                    ->orderByDesc('created_at'),
            ])
            ->first();
        $actorMap = [$actor->playerId => $actor];

        return [
            ...$this->eventPayload($event, $authorized),
            'player' => ['id' => $actor->playerId, 'name' => $actor->currentName],
            'plan' => $plan === null ? null : [
                'id' => (string) $plan->id,
                'status' => $plan->status->value,
                'windowStartsAt' => $plan->window_starts_at->toIso8601String(),
                'windowEndsAt' => $plan->window_ends_at->toIso8601String(),
                'appointments' => $plan->appointments
                    ->map(fn (KingPerkAppointment $appointment): array => $this->appointment($appointment, $plan, $actorMap))
                    ->all(),
                'requests' => $plan->requests
                    ->map(fn (KingPerkRequest $request): array => $this->request($request, $actorMap))
                    ->all(),
            ],
            'appointmentTypes' => $this->appointmentTypes(),
            'pushCategories' => $this->pushCategories(),
        ];
    }

    private function selectOccurrence(Event $event, ?string $occurrenceId): EventOccurrence
    {
        $event->loadMissing('occurrences');
        $occurrence = $occurrenceId === null
            ? $event->occurrences->sortBy('starts_at')->first()
            : $event->occurrences->firstWhere('id', $occurrenceId);

        if (! $occurrence instanceof EventOccurrence) {
            throw ValidationException::withMessages(['occurrence' => 'No matching Kingdom of Power occurrence was found.']);
        }

        return $occurrence;
    }

    private function loadPlan(EventOccurrence $occurrence, bool $management): ?KingPerkPlan
    {
        $query = KingPerkPlan::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['appointments', 'positionBlocks', 'skills', 'requests']);

        if (! $management) {
            $query->whereIn('status', [KingPerkPlanStatus::Published->value, KingPerkPlanStatus::Active->value]);
        }

        return $query->first();
    }

    /** @phpstan-return array<string, mixed> */
    private function eventPayload(Event $event, EventOccurrence $occurrence): array
    {
        $eventType = $event->eventType;
        $kingdom = $event->kingdom_id === null ? null : $this->kingdoms->find((string) $event->kingdom_id);
        if (! $eventType instanceof EventType || $kingdom === null) {
            throw ValidationException::withMessages([
                'event' => 'King Perks require a Kingdom-scoped Event with a valid Event type.',
            ]);
        }

        return [
            'event' => [
                'id' => (string) $event->id,
                'title' => $event->title,
                'typeSlug' => (string) $eventType->slug,
                'kingdomId' => (string) $event->kingdom_id,
                'kingdomName' => 'Kingdom #'.(string) $kingdom->number,
            ],
            'occurrence' => [
                'id' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
            ],
        ];
    }

    /** @phpstan-return array<string, mixed> */
    private function plan(KingPerkPlan $plan): array
    {
        $playerIds = array_values($plan->appointments->pluck('assigned_player_id')
            ->merge($plan->requests->pluck('player_id'))
            ->map(static fn ($id): string => (string) $id)
            ->unique()->values()->all());
        $players = $this->players->byIds($playerIds);

        return [
            'id' => (string) $plan->id,
            'status' => $plan->status->value,
            'windowStartsAt' => $plan->window_starts_at->toIso8601String(),
            'windowEndsAt' => $plan->window_ends_at->toIso8601String(),
            'publishedAt' => $plan->published_at?->toIso8601String(),
            'appointments' => $plan->appointments
                ->map(fn (KingPerkAppointment $appointment): array => $this->appointment($appointment, $plan, $players))->all(),
            'positionBlocks' => $plan->positionBlocks
                ->map(static fn (KingPerkPositionBlock $block): array => [
                    'id' => (string) $block->id,
                    'type' => $block->appointment_type->value,
                    'startsAt' => $block->starts_at->toIso8601String(),
                    'endsAt' => $block->ends_at->toIso8601String(),
                    'reason' => (string) $block->reason,
                ])->all(),
            'skills' => $plan->skills->map(fn (KingSkillPlan $skill): array => $this->skill($skill))->all(),
            'requests' => $plan->requests->map(fn (KingPerkRequest $request): array => $this->request($request, $players))->all(),
        ];
    }

    /**
     * @param  array<string, PlayerReference>  $players
     *
     * @phpstan-return array<string, mixed>
     */
    private function appointment(KingPerkAppointment $appointment, KingPerkPlan $plan, array $players): array
    {
        $player = $players[(string) $appointment->assigned_player_id] ?? null;

        return [
            'id' => (string) $appointment->id,
            'type' => $appointment->appointment_type->value,
            'typeLabel' => $appointment->appointment_type->label(),
            'playerId' => (string) $appointment->assigned_player_id,
            'playerName' => $player?->currentName,
            'playerEligible' => $player === null || $player->kingdomId === (string) $plan->kingdom_id,
            'startsAt' => $appointment->starts_at->toIso8601String(),
            'endsAt' => $appointment->ends_at->toIso8601String(),
            'durationMinutes' => $appointment->appointment_type->durationMinutes(),
            'playerCooldownMinutes' => $appointment->appointment_type->playerCooldownMinutes(),
            'playerCooldownAnchor' => $appointment->appointment_type->playerCooldownAnchor(),
            'status' => $appointment->status->value,
            'confirmedAt' => $appointment->confirmed_at?->toIso8601String(),
            'actualStartedAt' => $appointment->actual_started_at?->toIso8601String(),
            'actualEndedAt' => $appointment->actual_ended_at?->toIso8601String(),
            'notes' => $appointment->notes,
        ];
    }

    /**
     * @param  array<string, PlayerReference>  $players
     *
     * @phpstan-return array<string, mixed>
     */
    private function request(KingPerkRequest $request, array $players): array
    {
        $player = $players[(string) $request->player_id] ?? null;

        return [
            'id' => (string) $request->id,
            'playerId' => (string) $request->player_id,
            'playerName' => $player?->currentName,
            'category' => $request->push_category->value,
            'categoryLabel' => $request->push_category->label(),
            'preferredAppointmentType' => $request->preferred_appointment_type?->value,
            'availabilityStartsAt' => $request->availability_starts_at->toIso8601String(),
            'availabilityEndsAt' => $request->availability_ends_at->toIso8601String(),
            'plannedSpeedupMinutes' => $request->planned_speedup_minutes,
            'plannedResourceAmount' => $request->planned_resource_amount,
            'status' => $request->status->value,
            'scheduledAppointmentId' => $request->scheduled_appointment_id,
            'notes' => $request->notes,
        ];
    }

    /** @phpstan-return array<string, mixed> */
    private function skill(KingSkillPlan $skill): array
    {
        return [
            'id' => (string) $skill->id,
            'key' => $skill->skill_key->value,
            'label' => $skill->skill_key->label(),
            'plannedActivationAt' => $skill->planned_activation_at->toIso8601String(),
            'plannedEndsAt' => $skill->planned_ends_at->toIso8601String(),
            'effectDurationMinutes' => (int) $skill->effect_duration_minutes,
            'scheduleAvailableAt' => CarbonImmutable::instance($skill->planned_activation_at)
                ->subMinutes($skill->skill_key->advanceSchedulingMinutes())->toIso8601String(),
            'status' => $skill->status->value,
            'notes' => $skill->notes,
        ];
    }

    /** @phpstan-return array<string, mixed> */
    private function live(KingPerkPlan $plan): array
    {
        $playerIds = array_values($plan->appointments->pluck('assigned_player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());
        $players = $this->players->byIds($playerIds);
        $now = CarbonImmutable::now('UTC');
        $activeStatuses = [
            KingPerkAppointmentStatus::Scheduled->value,
            KingPerkAppointmentStatus::Confirmed->value,
            KingPerkAppointmentStatus::Active->value,
        ];
        $lanes = [];

        foreach (KingAppointmentType::cases() as $type) {
            $appointments = $plan->appointments
                ->filter(static fn (KingPerkAppointment $appointment): bool => $appointment->appointment_type === $type
                    && in_array($appointment->status->value, $activeStatuses, true))
                ->sortBy('starts_at')->values();
            $nowAppointment = $appointments->first(static fn (KingPerkAppointment $appointment): bool => $appointment->status === KingPerkAppointmentStatus::Active
                || (! $appointment->starts_at->isAfter($now) && $appointment->ends_at->isAfter($now)));
            $upcoming = $appointments->filter(static fn (KingPerkAppointment $appointment): bool => $appointment->starts_at->greaterThanOrEqualTo($now));
            if ($nowAppointment instanceof KingPerkAppointment) {
                $nowId = (string) $nowAppointment->id;
                $upcoming = $upcoming->reject(static fn (KingPerkAppointment $appointment): bool => (string) $appointment->id === $nowId);
            }
            $upcoming = $upcoming->values();

            $lanes[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'now' => $nowAppointment instanceof KingPerkAppointment ? $this->appointment($nowAppointment, $plan, $players) : null,
                'next' => ($next = $upcoming->get(0)) instanceof KingPerkAppointment ? $this->appointment($next, $plan, $players) : null,
                'following' => ($following = $upcoming->get(1)) instanceof KingPerkAppointment ? $this->appointment($following, $plan, $players) : null,
            ];
        }

        return ['generatedAt' => $now->toIso8601String(), 'lanes' => $lanes];
    }

    /** @phpstan-return list<array<string, mixed>> */
    private function appointmentTypes(): array
    {
        return array_map(static fn (KingAppointmentType $type): array => [
            'key' => $type->value,
            'label' => $type->label(),
            'durationMinutes' => $type->durationMinutes(),
            'playerCooldownMinutes' => $type->playerCooldownMinutes(),
            'playerCooldownAnchor' => $type->playerCooldownAnchor(),
            'cancelledPositionCooldownMinutes' => $type->cancelledPositionCooldownMinutes(),
            'recommendedFocus' => $type->recommendedFocus(),
        ], KingAppointmentType::cases());
    }

    /** @phpstan-return list<array<string, mixed>> */
    private function pushCategories(): array
    {
        return array_map(static fn (KingPerkPushCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'preferredAppointmentTypes' => array_map(static fn (KingAppointmentType $type): string => $type->value, $category->preferredAppointments()),
        ], KingPerkPushCategory::cases());
    }

    /** @phpstan-return list<array<string, mixed>> */
    private function skillTypes(): array
    {
        return array_map(static fn (KingSkill $skill): array => [
            'key' => $skill->value,
            'label' => $skill->label(),
            'recommendedFocus' => $skill->recommendedFocus(),
            'advanceSchedulingMinutes' => $skill->advanceSchedulingMinutes(),
        ], KingSkill::cases());
    }
}
