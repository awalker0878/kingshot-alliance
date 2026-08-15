<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkPlanStatus;
use App\Domain\KingPerks\Enums\KingPerkPushCategory;
use App\Domain\KingPerks\Enums\KingPerkRequestStatus;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingPerkRequest;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkRequestService
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function submit(
        Player $actor,
        KingPerkPlan $plan,
        KingPerkPushCategory $category,
        CarbonImmutable $availableFrom,
        CarbonImmutable $availableUntil,
        ?KingAppointmentType $preferredType = null,
        ?int $plannedSpeedupMinutes = null,
        ?int $plannedResourceAmount = null,
        ?string $notes = null,
    ): KingPerkRequest {
        return DB::transaction(function () use ($actor, $plan, $category, $availableFrom, $availableUntil, $preferredType, $plannedSpeedupMinutes, $plannedResourceAmount, $notes): KingPerkRequest {
            [$locked, $event, $currentActor] = $this->selfPlan($actor, $plan);

            if (! in_array($locked->status, [KingPerkPlanStatus::Published, KingPerkPlanStatus::Active], true)) {
                throw ValidationException::withMessages(['plan' => 'Appointment requests open after the King Perks schedule is published.']);
            }

            $start = $availableFrom->utc();
            $end = $availableUntil->utc();
            if (! $end->greaterThan($start)) {
                throw ValidationException::withMessages(['availability_ends_at' => 'Availability end must be after the start.']);
            }
            if ($start->lt($locked->window_starts_at) || $end->gt($locked->window_ends_at)) {
                throw ValidationException::withMessages(['availability_starts_at' => 'Availability must fit inside the preparation window.']);
            }
            if ($preferredType !== null && ! in_array($preferredType, $category->preferredAppointments(), true)) {
                throw ValidationException::withMessages(['preferred_appointment_type' => 'That appointment is not applicable to the selected preparation focus.']);
            }
            if ($plannedSpeedupMinutes !== null && ($plannedSpeedupMinutes < 0 || $plannedSpeedupMinutes > 5256000)) {
                throw ValidationException::withMessages(['planned_speedup_minutes' => 'Planned speedups must be between 0 and 5,256,000 minutes.']);
            }
            if ($plannedResourceAmount !== null && $plannedResourceAmount < 0) {
                throw ValidationException::withMessages(['planned_resource_amount' => 'Planned resource amount cannot be negative.']);
            }

            $request = KingPerkRequest::query()->create([
                'plan_id' => $locked->id,
                'player_id' => $currentActor->id,
                'push_category' => $category,
                'preferred_appointment_type' => $preferredType,
                'availability_starts_at' => $start,
                'availability_ends_at' => $end,
                'planned_speedup_minutes' => $plannedSpeedupMinutes,
                'planned_resource_amount' => $plannedResourceAmount,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'status' => KingPerkRequestStatus::Submitted,
            ]);

            $this->record('king_perks.request_submitted', $currentActor, $request, $event, [
                'push_category' => $category->value,
                'availability_starts_at' => $start->toIso8601String(),
                'availability_ends_at' => $end->toIso8601String(),
            ]);

            return $request->refresh();
        });
    }

    public function withdraw(Player $actor, KingPerkRequest $request): KingPerkRequest
    {
        return DB::transaction(function () use ($actor, $request): KingPerkRequest {
            [$record, , $event, $currentActor] = $this->selfRequest($actor, $request);

            if ($record->status === KingPerkRequestStatus::Scheduled) {
                throw ValidationException::withMessages(['request' => 'A scheduled request must be released by Kingdom leadership.']);
            }

            $record->forceFill(['status' => KingPerkRequestStatus::Withdrawn])->save();
            $this->record('king_perks.request_withdrawn', $currentActor, $record, $event);

            return $record->refresh();
        });
    }

    public function decline(Player $actor, KingPerkRequest $request): KingPerkRequest
    {
        return DB::transaction(function () use ($actor, $request): KingPerkRequest {
            [$record, , $event, $currentActor] = $this->managerRequest($actor, $request);

            if ($record->status !== KingPerkRequestStatus::Submitted) {
                throw ValidationException::withMessages(['request' => 'Only a submitted appointment request can be declined.']);
            }

            $record->forceFill([
                'status' => KingPerkRequestStatus::Declined,
                'reviewed_by_player_id' => $currentActor->id,
                'reviewed_at' => now(),
            ])->save();
            $this->record('king_perks.request_declined', $currentActor, $record, $event);

            return $record->refresh();
        });
    }

    public function markScheduled(
        Player $actor,
        KingPerkRequest $request,
        KingPerkAppointment $appointment,
    ): KingPerkRequest {
        return DB::transaction(function () use ($actor, $request, $appointment): KingPerkRequest {
            [$record, $plan, $event, $currentActor] = $this->managerRequest($actor, $request);
            $linked = KingPerkAppointment::query()
                ->whereKey($appointment->id)
                ->where('plan_id', $plan->id)
                ->where('assigned_player_id', $record->player_id)
                ->sharedLock()
                ->firstOrFail();

            if ($record->status !== KingPerkRequestStatus::Submitted) {
                throw ValidationException::withMessages(['request' => 'Only a submitted appointment request can be scheduled.']);
            }

            $record->forceFill([
                'status' => KingPerkRequestStatus::Scheduled,
                'scheduled_appointment_id' => $linked->id,
                'reviewed_by_player_id' => $currentActor->id,
                'reviewed_at' => now(),
            ])->save();
            $this->record('king_perks.request_scheduled', $currentActor, $record, $event, [
                'appointment_id' => (string) $linked->id,
            ]);

            return $record->refresh();
        });
    }

    /** @return array{KingPerkPlan,Event,Player} */
    private function selfPlan(Player $actor, KingPerkPlan $plan): array
    {
        $route = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($plan->id)->firstOrFail();
        $event = Event::query()->whereKey($route->event_id)->firstOrFail();
        $context = $this->mutations->requireSelf($actor, $event, $actor);
        $this->capabilities->require($context->event, EventCapability::KingPerks);

        if ((string) $context->actor->current_kingdom_id !== (string) $route->kingdom_id) {
            throw new AuthorizationException;
        }

        $locked = KingPerkPlan::query()
            ->whereKey($route->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $route->kingdom_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$locked, $context->event, $context->actor];
    }

    /** @return array{KingPerkRequest,KingPerkPlan,Event,Player} */
    private function selfRequest(Player $actor, KingPerkRequest $request): array
    {
        $route = KingPerkRequest::query()->select(['id', 'plan_id', 'player_id'])->whereKey($request->id)->firstOrFail();
        $planRoute = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($route->plan_id)->firstOrFail();
        $event = Event::query()->whereKey($planRoute->event_id)->firstOrFail();
        $context = $this->mutations->requireSelf($actor, $event, $actor);
        $this->capabilities->require($context->event, EventCapability::KingPerks);

        if ((string) $route->player_id !== (string) $context->actor->id
            || (string) $context->actor->current_kingdom_id !== (string) $planRoute->kingdom_id) {
            throw new AuthorizationException;
        }

        $plan = KingPerkPlan::query()
            ->whereKey($planRoute->id)
            ->where('event_id', $context->event->id)
            ->sharedLock()
            ->firstOrFail();
        $record = KingPerkRequest::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->where('player_id', $context->actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$record, $plan, $context->event, $context->actor];
    }

    /** @return array{KingPerkRequest,KingPerkPlan,Event,Player} */
    private function managerRequest(Player $actor, KingPerkRequest $request): array
    {
        $route = KingPerkRequest::query()->select(['id', 'plan_id'])->whereKey($request->id)->firstOrFail();
        $planRoute = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($route->plan_id)->firstOrFail();
        $event = Event::query()->whereKey($planRoute->event_id)->firstOrFail();
        $context = $this->mutations->requireManager($actor, $event);
        $this->capabilities->require($context->event, EventCapability::KingPerks);

        if (! $context->target instanceof Kingdom
            || (string) $context->target->id !== (string) $planRoute->kingdom_id) {
            throw new AuthorizationException;
        }

        $plan = KingPerkPlan::query()
            ->whereKey($planRoute->id)
            ->where('event_id', $context->event->id)
            ->where('kingdom_id', $context->target->id)
            ->lockForUpdate()
            ->firstOrFail();
        $record = KingPerkRequest::query()
            ->whereKey($route->id)
            ->where('plan_id', $plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$record, $plan, $context->event, $context->actor];
    }

    /** @param array<string, mixed> $metadata */
    private function record(string $name, Player $actor, KingPerkRequest $subject, Event $event, array $metadata = []): void
    {
        $metadata = [
            'event_id' => (string) $event->id,
            'kingdom_id' => (string) $event->kingdom_id,
            'actor_player_id' => (string) $actor->id,
            'request_player_id' => (string) $subject->player_id,
        ] + $metadata;

        $this->audit->record($name, $actor, $subject, null, $metadata);
        $this->outbox->record($name, null, $subject, $metadata, partitionKey: 'kingdom:'.$event->kingdom_id);
    }
}
