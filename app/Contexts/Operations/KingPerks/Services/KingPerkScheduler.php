<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingPerkPositionBlock;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkScheduler
{
    public function __construct(
        private EventAuthorization $mutations,
        private EventCapabilityGuard $capabilities,
        private KingPerkWindowResolver $windows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function createPlan(Player $actor, EventOccurrence $occurrence): KingPerkPlan
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        return DB::transaction(function () use ($actor, $occurrence, $event): KingPerkPlan {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::KingPerks);
            $kingdom = $this->requireKingdom($context->target);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = KingPerkPlan::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof KingPerkPlan) {
                return $existing->refresh();
            }

            $window = $this->windows->forOccurrence($lockedOccurrence);
            $plan = KingPerkPlan::query()->create([
                'event_id' => $context->event->id,
                'occurrence_id' => $lockedOccurrence->id,
                'kingdom_id' => $kingdom->id,
                'status' => KingPerkPlanStatus::Draft,
                'window_starts_at' => $window['starts_at'],
                'window_ends_at' => $window['ends_at'],
                'created_by_player_id' => $context->actor->id,
            ]);

            $this->record('king_perks.plan_created', $context->actor, $plan, $context->event, [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'window_starts_at' => $window['starts_at']->toIso8601String(),
                'window_ends_at' => $window['ends_at']->toIso8601String(),
            ]);

            return $plan->refresh();
        });
    }

    public function publishPlan(Player $actor, KingPerkPlan $plan): KingPerkPlan
    {
        return DB::transaction(function () use ($actor, $plan): KingPerkPlan {
            [$locked, $event, $currentActor] = $this->manager($actor, $plan);

            if ($locked->status === KingPerkPlanStatus::Closed) {
                throw ValidationException::withMessages(['plan' => 'A closed King Perks plan cannot be published.']);
            }

            $locked->forceFill([
                'status' => KingPerkPlanStatus::Published,
                'published_by_player_id' => $currentActor->id,
                'published_at' => now(),
            ])->save();

            $this->record('king_perks.plan_published', $currentActor, $locked, $event);

            return $locked->refresh();
        });
    }

    public function assignAppointment(
        Player $actor,
        KingPerkPlan $plan,
        KingAppointmentType $type,
        Player $target,
        CarbonImmutable $startsAt,
        ?string $notes = null,
        ?KingPerkAppointment $appointment = null,
    ): KingPerkAppointment {
        return DB::transaction(function () use ($actor, $plan, $type, $target, $startsAt, $notes, $appointment): KingPerkAppointment {
            [$lockedPlan, $event, $currentActor] = $this->manager($actor, $plan);
            $this->assertOpenPlan($lockedPlan);

            $currentTarget = Player::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ((string) $currentTarget->current_kingdom_id !== (string) $lockedPlan->kingdom_id) {
                throw ValidationException::withMessages(['player_id' => 'The selected Player is not currently in this Kingdom.']);
            }

            $start = $startsAt->utc();
            $end = $start->addMinutes($type->durationMinutes());
            $this->assertInsideWindow($lockedPlan, $start, $end);

            $record = null;
            if ($appointment instanceof KingPerkAppointment) {
                $record = KingPerkAppointment::query()
                    ->whereKey($appointment->id)
                    ->where('plan_id', $lockedPlan->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                    throw ValidationException::withMessages(['appointment_id' => 'Only a future scheduled or confirmed appointment can be edited in place.']);
                }
            }

            $ignoreId = $record?->id;
            $this->assertPositionAvailable($lockedPlan, $type, $start, $end, $ignoreId);
            $this->assertPlayerAvailable($lockedPlan, $currentTarget, $type, $start, $end, $ignoreId);

            $created = ! $record instanceof KingPerkAppointment;
            $record ??= new KingPerkAppointment(['plan_id' => $lockedPlan->id]);
            $record->forceFill([
                'appointment_type' => $type,
                'assigned_player_id' => $currentTarget->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => KingPerkAppointmentStatus::Scheduled,
                'assigned_by_player_id' => $currentActor->id,
                'confirmed_at' => null,
                'actual_started_at' => null,
                'actual_ended_at' => null,
                'completed_at' => null,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ])->save();

            $this->record(
                $created ? 'king_perks.appointment_assigned' : 'king_perks.appointment_reassigned',
                $currentActor,
                $record,
                $event,
                [
                    'appointment_type' => $type->value,
                    'target_player_id' => (string) $currentTarget->id,
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $end->toIso8601String(),
                    'duration_minutes' => $type->durationMinutes(),
                ],
            );

            return $record->refresh();
        });
    }

    public function confirmAppointment(Player $actor, KingPerkAppointment $appointment): KingPerkAppointment
    {
        return DB::transaction(function () use ($actor, $appointment): KingPerkAppointment {
            [$record, $plan, $event, $currentActor] = $this->selfAppointment($actor, $appointment);

            if ($record->status === KingPerkAppointmentStatus::Confirmed) {
                return $record->refresh();
            }
            if ($record->status !== KingPerkAppointmentStatus::Scheduled) {
                throw ValidationException::withMessages(['appointment' => 'Only a scheduled appointment can be confirmed.']);
            }

            $record->forceFill([
                'status' => KingPerkAppointmentStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();

            $this->record('king_perks.appointment_confirmed', $currentActor, $record, $event, [
                'plan_id' => (string) $plan->id,
            ]);

            return $record->refresh();
        });
    }

    public function declineAppointment(Player $actor, KingPerkAppointment $appointment): KingPerkAppointment
    {
        return DB::transaction(function () use ($actor, $appointment): KingPerkAppointment {
            [$record, $plan, $event, $currentActor] = $this->selfAppointment($actor, $appointment);

            if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only a future scheduled or confirmed appointment can be declined.']);
            }

            $record->forceFill(['status' => KingPerkAppointmentStatus::Cancelled])->save();
            $this->record('king_perks.appointment_declined', $currentActor, $record, $event, [
                'plan_id' => (string) $plan->id,
            ]);

            return $record->refresh();
        });
    }

    public function markAppointmentActive(Player $actor, KingPerkAppointment $appointment): KingPerkAppointment
    {
        return DB::transaction(function () use ($actor, $appointment): KingPerkAppointment {
            [$record, $plan, $event, $currentActor] = $this->managerAppointment($actor, $appointment);

            if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only a scheduled or confirmed appointment can be marked active.']);
            }

            $record->forceFill([
                'status' => KingPerkAppointmentStatus::Active,
                'actual_started_at' => now(),
            ])->save();
            $this->record('king_perks.appointment_activated', $currentActor, $record, $event, [
                'plan_id' => (string) $plan->id,
            ]);

            return $record->refresh();
        });
    }

    public function markAppointment(
        Player $actor,
        KingPerkAppointment $appointment,
        KingPerkAppointmentStatus $status,
    ): KingPerkAppointment {
        if (! in_array($status, [KingPerkAppointmentStatus::Completed, KingPerkAppointmentStatus::NoShow], true)) {
            throw ValidationException::withMessages(['status' => 'Only completed or no-show may be recorded here.']);
        }

        return DB::transaction(function () use ($actor, $appointment, $status): KingPerkAppointment {
            [$record, , $event, $currentActor] = $this->managerAppointment($actor, $appointment);

            if (in_array($record->status, [KingPerkAppointmentStatus::Cancelled, KingPerkAppointmentStatus::Completed], true)) {
                throw ValidationException::withMessages(['appointment' => 'This appointment is already final.']);
            }

            $record->forceFill([
                'status' => $status,
                'actual_ended_at' => $status === KingPerkAppointmentStatus::Completed ? now() : $record->actual_ended_at,
                'completed_at' => $status === KingPerkAppointmentStatus::Completed ? now() : null,
            ])->save();

            $this->record(
                $status === KingPerkAppointmentStatus::Completed
                    ? 'king_perks.appointment_completed'
                    : 'king_perks.appointment_no_show',
                $currentActor,
                $record,
                $event,
            );

            return $record->refresh();
        });
    }

    public function recordCancelledPositionCooldown(
        Player $actor,
        KingPerkAppointment $appointment,
        CarbonImmutable $cancelledAt,
    ): KingPerkPositionBlock {
        return DB::transaction(function () use ($actor, $appointment, $cancelledAt): KingPerkPositionBlock {
            [$record, $plan, $event, $currentActor] = $this->managerAppointment($actor, $appointment);

            if ($record->status === KingPerkAppointmentStatus::Completed) {
                throw ValidationException::withMessages(['appointment' => 'A completed appointment cannot be cancelled.']);
            }

            $type = $record->appointment_type;
            $start = $cancelledAt->utc();
            $end = $start->addMinutes($type->cancelledPositionCooldownMinutes());

            $block = KingPerkPositionBlock::query()->create([
                'plan_id' => $plan->id,
                'appointment_type' => $type,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => 'cancelled_appointment',
                'source_appointment_id' => $record->id,
                'recorded_by_player_id' => $currentActor->id,
            ]);

            $record->forceFill([
                'status' => KingPerkAppointmentStatus::Cancelled,
                'actual_ended_at' => $record->actual_started_at === null ? null : now(),
            ])->save();
            $this->record('king_perks.appointment_cancelled', $currentActor, $record, $event, [
                'position_block_ends_at' => $end->toIso8601String(),
            ]);

            return $block->refresh();
        });
    }

    public function planSkill(
        Player $actor,
        KingPerkPlan $plan,
        KingSkill $skill,
        CarbonImmutable $activationAt,
        int $effectDurationMinutes,
        ?string $notes = null,
    ): KingSkillPlan {
        if ($effectDurationMinutes < 1 || $effectDurationMinutes > 10080) {
            throw ValidationException::withMessages(['effect_duration_minutes' => 'Skill duration must be between 1 minute and 7 days.']);
        }

        return DB::transaction(function () use ($actor, $plan, $skill, $activationAt, $effectDurationMinutes, $notes): KingSkillPlan {
            [$lockedPlan, $event, $currentActor] = $this->manager($actor, $plan);
            $this->assertOpenPlan($lockedPlan);
            $activation = $activationAt->utc();
            if ($activation->lt($lockedPlan->window_starts_at) || $activation->gt($lockedPlan->window_ends_at)) {
                throw ValidationException::withMessages(['planned_activation_at' => 'King Skill activation must begin during the preparation window.']);
            }

            $record = KingSkillPlan::query()->create([
                'plan_id' => $lockedPlan->id,
                'skill_key' => $skill,
                'planned_activation_at' => $activation,
                'effect_duration_minutes' => $effectDurationMinutes,
                'planned_ends_at' => $activation->addMinutes($effectDurationMinutes),
                'status' => KingSkillStatus::Planned,
                'planned_by_player_id' => $currentActor->id,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ]);

            $this->record('king_perks.skill_planned', $currentActor, $record, $event, [
                'skill_key' => $skill->value,
                'planned_activation_at' => $activation->toIso8601String(),
                'effect_duration_minutes' => $effectDurationMinutes,
                'schedule_available_at' => $activation->subMinutes($skill->advanceSchedulingMinutes())->toIso8601String(),
            ]);

            return $record->refresh();
        });
    }

    public function markSkillScheduled(Player $actor, KingSkillPlan $skill): KingSkillPlan
    {
        return $this->transitionSkill($actor, $skill, KingSkillStatus::ScheduledInGame, 'king_perks.skill_scheduled');
    }

    public function markSkillActivated(Player $actor, KingSkillPlan $skill): KingSkillPlan
    {
        return $this->transitionSkill($actor, $skill, KingSkillStatus::Activated, 'king_perks.skill_activated');
    }

    /** @return array{KingPerkAppointment,KingPerkPlan,Event,Player} */
    private function selfAppointment(Player $actor, KingPerkAppointment $appointment): array
    {
        $route = KingPerkAppointment::query()->select(['id', 'plan_id', 'assigned_player_id'])->whereKey($appointment->id)->firstOrFail();
        $planRoute = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($route->plan_id)->firstOrFail();
        $event = Event::query()->whereKey($planRoute->event_id)->firstOrFail();
        $context = $this->mutations->requireSelf($actor, $event, $actor);
        $this->capabilities->require($context->event, EventCapability::KingPerks);

        if ((string) $route->assigned_player_id !== (string) $context->actor->id
            || (string) $context->actor->current_kingdom_id !== (string) $planRoute->kingdom_id) {
            throw new AuthorizationException;
        }

        $plan = KingPerkPlan::query()
            ->whereKey($planRoute->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $planRoute->kingdom_id)
            ->sharedLock()
            ->firstOrFail();
        $record = KingPerkAppointment::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->where('assigned_player_id', $context->actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$record, $plan, $context->event, $context->actor];
    }

    /** @return array{KingPerkAppointment,KingPerkPlan,Event,Player} */
    private function managerAppointment(Player $actor, KingPerkAppointment $appointment): array
    {
        $route = KingPerkAppointment::query()->select(['id', 'plan_id'])->whereKey($appointment->id)->firstOrFail();
        $planRoute = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($route->plan_id)->firstOrFail();
        [$plan, $event, $currentActor] = $this->manager($actor, $planRoute);
        $record = KingPerkAppointment::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$record, $plan, $event, $currentActor];
    }

    /** @return array{KingPerkPlan, Event, Player} */
    private function manager(Player $actor, KingPerkPlan $plan): array
    {
        $route = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($plan->id)->firstOrFail();
        $event = Event::query()->whereKey($route->event_id)->firstOrFail();
        $context = $this->mutations->requireManager($actor, $event);
        $this->capabilities->require($context->event, EventCapability::KingPerks);
        $kingdom = $this->requireKingdom($context->target);

        if ((string) $route->kingdom_id !== (string) $kingdom->id) {
            throw new AuthorizationException;
        }

        $locked = KingPerkPlan::query()
            ->whereKey($route->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $kingdom->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$locked, $context->event, $context->actor];
    }

    private function requireKingdom(object $target): Kingdom
    {
        if (! $target instanceof Kingdom) {
            throw ValidationException::withMessages(['event' => 'King Perks require a Kingdom Event target.']);
        }

        return $target;
    }

    private function assertOpenPlan(KingPerkPlan $plan): void
    {
        if ($plan->status === KingPerkPlanStatus::Closed) {
            throw ValidationException::withMessages(['plan' => 'This King Perks plan is closed.']);
        }
    }

    private function assertInsideWindow(KingPerkPlan $plan, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($start->lt($plan->window_starts_at) || $end->gt($plan->window_ends_at)) {
            throw ValidationException::withMessages([
                'starts_at' => 'The entire appointment must fit inside the Kingdom of Power preparation window.',
            ]);
        }
    }

    private function assertPositionAvailable(
        KingPerkPlan $plan,
        KingAppointmentType $type,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $ignoreId,
    ): void {
        $appointments = KingPerkAppointment::query()
            ->where('plan_id', $plan->id)
            ->where('appointment_type', $type->value)
            ->whereNotIn('status', [KingPerkAppointmentStatus::Cancelled->value, KingPerkAppointmentStatus::NoShow->value])
            ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        $blocked = KingPerkPositionBlock::query()
            ->where('plan_id', $plan->id)
            ->where('appointment_type', $type->value)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($appointments || $blocked) {
            throw ValidationException::withMessages(['starts_at' => 'This appointment position is occupied or cooling down for the requested interval.']);
        }
    }

    private function assertPlayerAvailable(
        KingPerkPlan $plan,
        Player $player,
        KingAppointmentType $newType,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $ignoreId,
    ): void {
        $appointments = KingPerkAppointment::query()
            ->where('plan_id', $plan->id)
            ->where('assigned_player_id', $player->id)
            ->whereNotIn('status', [KingPerkAppointmentStatus::Cancelled->value, KingPerkAppointmentStatus::NoShow->value])
            ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->get(['id', 'appointment_type', 'starts_at', 'ends_at', 'player_cooldown_ends_at']);

        $newBlockedUntil = $end->addMinutes($newType->playerCooldownMinutes());
        foreach ($appointments as $existing) {
            $existingBlockedUntil = CarbonImmutable::instance($existing->player_cooldown_ends_at);
            $existingStart = CarbonImmutable::instance($existing->starts_at);

            if ($start->lt($existingBlockedUntil) && $existingStart->lt($newBlockedUntil)) {
                throw ValidationException::withMessages([
                    'player_id' => 'The selected Player is already appointed or inside the post-appointment cooldown window.',
                ]);
            }
        }
    }

    private function transitionSkill(
        Player $actor,
        KingSkillPlan $skill,
        KingSkillStatus $status,
        string $eventName,
    ): KingSkillPlan {
        return DB::transaction(function () use ($actor, $skill, $status, $eventName): KingSkillPlan {
            $route = KingSkillPlan::query()->select(['id', 'plan_id'])->whereKey($skill->id)->firstOrFail();
            $planRoute = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($route->plan_id)->firstOrFail();
            [$plan, $event, $currentActor] = $this->manager($actor, $planRoute);
            $record = KingSkillPlan::query()
                ->whereKey($route->id)
                ->where('plan_id', $plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($status === KingSkillStatus::ScheduledInGame && $record->status !== KingSkillStatus::Planned) {
                throw ValidationException::withMessages(['skill' => 'Only a planned King Skill can be marked scheduled in game.']);
            }
            if ($status === KingSkillStatus::Activated && ! in_array($record->status, [KingSkillStatus::Planned, KingSkillStatus::ScheduledInGame], true)) {
                throw ValidationException::withMessages(['skill' => 'Only a planned or scheduled King Skill can be marked activated.']);
            }

            $attributes = ['status' => $status];
            if ($status === KingSkillStatus::ScheduledInGame) {
                $attributes['scheduled_by_player_id'] = $currentActor->id;
                $attributes['scheduled_in_game_at'] = now();
            }
            if ($status === KingSkillStatus::Activated) {
                $attributes['activated_by_player_id'] = $currentActor->id;
                $attributes['activated_at'] = now();
            }
            $record->forceFill($attributes)->save();
            $this->record($eventName, $currentActor, $record, $event);

            return $record->refresh();
        });
    }

    /** @param array<string, mixed> $metadata */
    private function record(
        string $name,
        Player $actor,
        Model $subject,
        Event $event,
        array $metadata = [],
    ): void {
        $metadata = [
            'event_id' => (string) $event->id,
            'kingdom_id' => (string) $event->kingdom_id,
            'actor_player_id' => (string) $actor->id,
        ] + $metadata;

        $this->audit->record($name, $actor, $subject, null, $metadata);
        $this->outbox->record(
            $name,
            null,
            $subject,
            $metadata,
            partitionKey: 'kingdom:'.$event->kingdom_id,
        );
    }
}
