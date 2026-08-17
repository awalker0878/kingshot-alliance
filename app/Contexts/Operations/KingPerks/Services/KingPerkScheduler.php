<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingPerkPositionBlock;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Contexts\Operations\KingPerks\Services\Internal\KingPerkWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkScheduler
{
    public function __construct(
        private KingPerkWriteState $writeState,
        private PlayerReferenceQuery $players,
        private KingPerkWindowResolver $windows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function createPlan(string $actorPlayerId, string $eventId, string $occurrenceId): void
    {
        DB::transaction(function () use ($actorPlayerId, $eventId, $occurrenceId): void {
            $context = $this->writeState->managerEvent($actorPlayerId, $eventId);
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (KingPerkPlan::query()->where('occurrence_id', $lockedOccurrence->id)->lockForUpdate()->exists()) {
                return;
            }

            $window = $this->windows->forOccurrence($lockedOccurrence);
            $plan = KingPerkPlan::query()->create([
                'event_id' => $context->event->id,
                'occurrence_id' => $lockedOccurrence->id,
                'kingdom_id' => $context->target->kingdomId,
                'status' => KingPerkPlanStatus::Draft,
                'window_starts_at' => $window['starts_at'],
                'window_ends_at' => $window['ends_at'],
                'created_by_player_id' => $context->actor->playerId,
            ]);

            $this->record('king_perks.plan_created', $context->actor, $plan, $context->event, [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'window_starts_at' => $window['starts_at']->toIso8601String(),
                'window_ends_at' => $window['ends_at']->toIso8601String(),
            ]);
        });
    }

    public function publishPlan(string $actorPlayerId, string $planId): void
    {
        DB::transaction(function () use ($actorPlayerId, $planId): void {
            [$plan, $event, $actor] = $this->writeState->managerPlan($actorPlayerId, $planId);
            if ($plan->status === KingPerkPlanStatus::Closed) {
                throw ValidationException::withMessages(['plan' => 'A closed King Perks plan cannot be published.']);
            }

            $plan->forceFill([
                'status' => KingPerkPlanStatus::Published,
                'published_by_player_id' => $actor->playerId,
                'published_at' => now(),
            ])->save();
            $this->record('king_perks.plan_published', $actor, $plan, $event);
        });
    }

    public function assignAppointment(
        string $actorPlayerId,
        string $planId,
        KingAppointmentType $type,
        string $targetPlayerId,
        CarbonImmutable $startsAt,
        ?string $notes = null,
        ?string $appointmentId = null,
    ): string {
        return DB::transaction(function () use ($actorPlayerId, $planId, $type, $targetPlayerId, $startsAt, $notes, $appointmentId): string {
            [$plan, $event, $actor] = $this->writeState->managerPlan($actorPlayerId, $planId);
            $record = $this->assignLocked($plan, $event, $actor, $type, $targetPlayerId, $startsAt, $notes, $appointmentId);

            return (string) $record->id;
        });
    }

    public function confirmAppointment(string $actorPlayerId, string $appointmentId): void
    {
        DB::transaction(function () use ($actorPlayerId, $appointmentId): void {
            [$record, $plan, $event, $actor] = $this->writeState->selfAppointment($actorPlayerId, $appointmentId);
            if ($record->status === KingPerkAppointmentStatus::Confirmed) {
                return;
            }
            if ($record->status !== KingPerkAppointmentStatus::Scheduled) {
                throw ValidationException::withMessages(['appointment' => 'Only a scheduled appointment can be confirmed.']);
            }

            $record->forceFill(['status' => KingPerkAppointmentStatus::Confirmed, 'confirmed_at' => now()])->save();
            $this->record('king_perks.appointment_confirmed', $actor, $record, $event, ['plan_id' => (string) $plan->id]);
        });
    }

    public function declineAppointment(string $actorPlayerId, string $appointmentId): void
    {
        DB::transaction(function () use ($actorPlayerId, $appointmentId): void {
            [$record, $plan, $event, $actor] = $this->writeState->selfAppointment($actorPlayerId, $appointmentId);
            if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only a future scheduled or confirmed appointment can be declined.']);
            }

            $record->forceFill(['status' => KingPerkAppointmentStatus::Cancelled])->save();
            $this->record('king_perks.appointment_declined', $actor, $record, $event, ['plan_id' => (string) $plan->id]);
        });
    }

    public function markAppointmentActive(string $actorPlayerId, string $appointmentId): void
    {
        DB::transaction(function () use ($actorPlayerId, $appointmentId): void {
            [$record, $plan, $event, $actor] = $this->writeState->managerAppointment($actorPlayerId, $appointmentId);
            if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only a scheduled or confirmed appointment can be marked active.']);
            }

            $record->forceFill(['status' => KingPerkAppointmentStatus::Active, 'actual_started_at' => now()])->save();
            $this->record('king_perks.appointment_activated', $actor, $record, $event, ['plan_id' => (string) $plan->id]);
        });
    }

    public function markAppointment(string $actorPlayerId, string $appointmentId, KingPerkAppointmentStatus $status): void
    {
        if (! in_array($status, [KingPerkAppointmentStatus::Completed, KingPerkAppointmentStatus::NoShow], true)) {
            throw ValidationException::withMessages(['status' => 'Only completed or no-show may be recorded here.']);
        }

        DB::transaction(function () use ($actorPlayerId, $appointmentId, $status): void {
            [$record, , $event, $actor] = $this->writeState->managerAppointment($actorPlayerId, $appointmentId);
            $this->markOutcomeLocked($record, $event, $actor, $status);
        });
    }

    public function replaceNoShowAppointment(string $actorPlayerId, string $appointmentId, string $replacementPlayerId): void
    {
        DB::transaction(function () use ($actorPlayerId, $appointmentId, $replacementPlayerId): void {
            [$original, $plan, $event, $actor] = $this->writeState->managerAppointment($actorPlayerId, $appointmentId);
            $type = $original->appointmentType();
            $startsAt = $original->startsAt();
            $this->markOutcomeLocked($original, $event, $actor, KingPerkAppointmentStatus::NoShow);

            $replacement = $this->assignLocked(
                $plan,
                $event,
                $actor,
                $type,
                $replacementPlayerId,
                $startsAt,
                'Live replacement for no-show appointment '.(string) $original->id,
            );

            $now = CarbonImmutable::now('UTC');
            if (! $now->lt($replacement->startsAt()) && $now->lt($replacement->endsAt())) {
                $replacement->forceFill([
                    'status' => KingPerkAppointmentStatus::Active,
                    'actual_started_at' => now(),
                ])->save();
                $this->record('king_perks.appointment_activated', $actor, $replacement, $event, ['plan_id' => (string) $plan->id]);
            }
        });
    }

    public function recordCancelledPositionCooldown(
        string $actorPlayerId,
        string $appointmentId,
        CarbonImmutable $cancelledAt,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $appointmentId, $cancelledAt): void {
            [$record, $plan, $event, $actor] = $this->writeState->managerAppointment($actorPlayerId, $appointmentId);
            if ($record->status === KingPerkAppointmentStatus::Completed) {
                throw ValidationException::withMessages(['appointment' => 'A completed appointment cannot be cancelled.']);
            }

            $start = $cancelledAt->utc();
            $end = $start->addMinutes($record->appointment_type->cancelledPositionCooldownMinutes());
            KingPerkPositionBlock::query()->create([
                'plan_id' => $plan->id,
                'appointment_type' => $record->appointment_type,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => 'cancelled_appointment',
                'source_appointment_id' => $record->id,
                'recorded_by_player_id' => $actor->playerId,
            ]);

            $record->forceFill([
                'status' => KingPerkAppointmentStatus::Cancelled,
                'actual_ended_at' => $record->actual_started_at === null ? null : now(),
            ])->save();
            $this->record('king_perks.appointment_cancelled', $actor, $record, $event, [
                'position_block_ends_at' => $end->toIso8601String(),
            ]);
        });
    }

    public function planSkill(
        string $actorPlayerId,
        string $planId,
        KingSkill $skill,
        CarbonImmutable $activationAt,
        int $effectDurationMinutes,
        ?string $notes = null,
    ): void {
        if ($effectDurationMinutes < 1 || $effectDurationMinutes > 10080) {
            throw ValidationException::withMessages(['effect_duration_minutes' => 'Skill duration must be between 1 minute and 7 days.']);
        }

        DB::transaction(function () use ($actorPlayerId, $planId, $skill, $activationAt, $effectDurationMinutes, $notes): void {
            [$plan, $event, $actor] = $this->writeState->managerPlan($actorPlayerId, $planId);
            $this->assertOpenPlan($plan);
            $activation = $activationAt->utc();
            if ($activation->lt($plan->window_starts_at) || $activation->gt($plan->window_ends_at)) {
                throw ValidationException::withMessages(['planned_activation_at' => 'King Skill activation must begin during the preparation window.']);
            }

            $record = KingSkillPlan::query()->create([
                'plan_id' => $plan->id,
                'skill_key' => $skill,
                'planned_activation_at' => $activation,
                'effect_duration_minutes' => $effectDurationMinutes,
                'planned_ends_at' => $activation->addMinutes($effectDurationMinutes),
                'status' => KingSkillStatus::Planned,
                'planned_by_player_id' => $actor->playerId,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
            ]);
            $this->record('king_perks.skill_planned', $actor, $record, $event, [
                'skill_key' => $skill->value,
                'planned_activation_at' => $activation->toIso8601String(),
                'effect_duration_minutes' => $effectDurationMinutes,
                'schedule_available_at' => $activation->subMinutes($skill->advanceSchedulingMinutes())->toIso8601String(),
            ]);
        });
    }

    public function markSkillScheduled(string $actorPlayerId, string $skillId): void
    {
        $this->transitionSkill($actorPlayerId, $skillId, KingSkillStatus::ScheduledInGame, 'king_perks.skill_scheduled');
    }

    public function markSkillActivated(string $actorPlayerId, string $skillId): void
    {
        $this->transitionSkill($actorPlayerId, $skillId, KingSkillStatus::Activated, 'king_perks.skill_activated');
    }

    private function assignLocked(
        KingPerkPlan $plan,
        Event $event,
        PlayerReference $actor,
        KingAppointmentType $type,
        string $targetPlayerId,
        CarbonImmutable $startsAt,
        ?string $notes = null,
        ?string $appointmentId = null,
    ): KingPerkAppointment {
        $this->assertOpenPlan($plan);
        $target = $this->players->lockCurrent($targetPlayerId);
        if ($target->kingdomId !== (string) $plan->kingdom_id) {
            throw ValidationException::withMessages(['player_id' => 'The selected Player is not currently in this Kingdom.']);
        }

        $start = $startsAt->utc();
        $end = $start->addMinutes($type->durationMinutes());
        $this->assertInsideWindow($plan, $start, $end);

        $record = null;
        if ($appointmentId !== null) {
            $record = KingPerkAppointment::query()
                ->whereKey($appointmentId)
                ->where('plan_id', $plan->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($record->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['appointment_id' => 'Only a future scheduled or confirmed appointment can be edited in place.']);
            }
        }

        $this->assertPositionAvailable($plan, $type, $start, $end, $record?->id);
        $this->assertPlayerAvailable($plan, $target->playerId, $type, $start, $end, $record?->id);
        $created = ! $record instanceof KingPerkAppointment;
        $record ??= new KingPerkAppointment(['plan_id' => $plan->id]);
        $record->forceFill([
            'appointment_type' => $type,
            'assigned_player_id' => $target->playerId,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => KingPerkAppointmentStatus::Scheduled,
            'assigned_by_player_id' => $actor->playerId,
            'confirmed_at' => null,
            'actual_started_at' => null,
            'actual_ended_at' => null,
            'completed_at' => null,
            'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
        ])->save();

        $this->record(
            $created ? 'king_perks.appointment_assigned' : 'king_perks.appointment_reassigned',
            $actor,
            $record,
            $event,
            [
                'appointment_type' => $type->value,
                'target_player_id' => $target->playerId,
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $end->toIso8601String(),
                'duration_minutes' => $type->durationMinutes(),
            ],
        );

        return $record;
    }

    private function markOutcomeLocked(
        KingPerkAppointment $record,
        Event $event,
        PlayerReference $actor,
        KingPerkAppointmentStatus $status,
    ): void {
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
            $actor,
            $record,
            $event,
        );
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
        string $playerId,
        KingAppointmentType $newType,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $ignoreId,
    ): void {
        $appointments = KingPerkAppointment::query()
            ->where('plan_id', $plan->id)
            ->where('assigned_player_id', $playerId)
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
        string $actorPlayerId,
        string $skillId,
        KingSkillStatus $status,
        string $eventName,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $skillId, $status, $eventName): void {
            [$record, , $event, $actor] = $this->writeState->managerSkill($actorPlayerId, $skillId);
            if ($status === KingSkillStatus::ScheduledInGame && $record->status !== KingSkillStatus::Planned) {
                throw ValidationException::withMessages(['skill' => 'Only a planned King Skill can be marked scheduled in game.']);
            }
            if ($status === KingSkillStatus::Activated
                && ! in_array($record->status, [KingSkillStatus::Planned, KingSkillStatus::ScheduledInGame], true)) {
                throw ValidationException::withMessages(['skill' => 'Only a planned or scheduled King Skill can be marked activated.']);
            }

            $attributes = ['status' => $status];
            if ($status === KingSkillStatus::ScheduledInGame) {
                $attributes['scheduled_by_player_id'] = $actor->playerId;
                $attributes['scheduled_in_game_at'] = now();
            }
            if ($status === KingSkillStatus::Activated) {
                $attributes['activated_by_player_id'] = $actor->playerId;
                $attributes['activated_at'] = now();
            }
            $record->forceFill($attributes)->save();
            $this->record($eventName, $actor, $record, $event);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function record(
        string $name,
        PlayerReference $actor,
        Model $subject,
        Event $event,
        array $metadata = [],
    ): void {
        $metadata = [
            'event_id' => (string) $event->id,
            'kingdom_id' => (string) $event->kingdom_id,
            'actor_player_id' => $actor->playerId,
        ] + $metadata;

        $this->audit->record($name, $actor, $subject, null, $metadata);
        $this->outbox->record($name, null, $subject, $metadata, partitionKey: 'kingdom:'.$event->kingdom_id);
    }
}
