<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingPerkRequestStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkRequest;
use App\Contexts\Operations\KingPerks\Services\Internal\KingPerkWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkRequestService
{
    public function __construct(
        private KingPerkWriteState $writeState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function submit(
        string $actorPlayerId,
        string $planId,
        KingPerkPushCategory $category,
        CarbonImmutable $availableFrom,
        CarbonImmutable $availableUntil,
        ?KingAppointmentType $preferredType = null,
        ?int $plannedSpeedupMinutes = null,
        ?int $plannedResourceAmount = null,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $planId, $category, $availableFrom, $availableUntil, $preferredType, $plannedSpeedupMinutes, $plannedResourceAmount, $notes): void {
            [$plan, $event, $actor] = $this->writeState->selfPlan($actorPlayerId, $planId);
            if (! in_array($plan->status, [KingPerkPlanStatus::Published, KingPerkPlanStatus::Active], true)) {
                throw ValidationException::withMessages(['plan' => 'Appointment requests open after the King Perks schedule is published.']);
            }

            $start = $availableFrom->utc();
            $end = $availableUntil->utc();
            if (! $end->greaterThan($start)) {
                throw ValidationException::withMessages(['availability_ends_at' => 'Availability end must be after the start.']);
            }
            if ($start->lt($plan->window_starts_at) || $end->gt($plan->window_ends_at)) {
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
                'plan_id' => $plan->id,
                'player_id' => $actor->playerId,
                'push_category' => $category,
                'preferred_appointment_type' => $preferredType,
                'availability_starts_at' => $start,
                'availability_ends_at' => $end,
                'planned_speedup_minutes' => $plannedSpeedupMinutes,
                'planned_resource_amount' => $plannedResourceAmount,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'status' => KingPerkRequestStatus::Submitted,
            ]);

            $this->record('king_perks.request_submitted', $actor, $request, $event, [
                'push_category' => $category->value,
                'availability_starts_at' => $start->toIso8601String(),
                'availability_ends_at' => $end->toIso8601String(),
            ]);
        });
    }

    public function withdraw(string $actorPlayerId, string $requestId): void
    {
        DB::transaction(function () use ($actorPlayerId, $requestId): void {
            [$request, , $event, $actor] = $this->writeState->selfRequest($actorPlayerId, $requestId);
            if ($request->status === KingPerkRequestStatus::Scheduled) {
                throw ValidationException::withMessages(['request' => 'A scheduled request must be released by Kingdom leadership.']);
            }

            $request->forceFill(['status' => KingPerkRequestStatus::Withdrawn])->save();
            $this->record('king_perks.request_withdrawn', $actor, $request, $event);
        });
    }

    public function decline(string $actorPlayerId, string $requestId): void
    {
        DB::transaction(function () use ($actorPlayerId, $requestId): void {
            [$request, , $event, $actor] = $this->writeState->managerRequest($actorPlayerId, $requestId);
            if ($request->status !== KingPerkRequestStatus::Submitted) {
                throw ValidationException::withMessages(['request' => 'Only a submitted appointment request can be declined.']);
            }

            $request->forceFill([
                'status' => KingPerkRequestStatus::Declined,
                'reviewed_by_player_id' => $actor->playerId,
                'reviewed_at' => now(),
            ])->save();
            $this->record('king_perks.request_declined', $actor, $request, $event);
        });
    }

    public function markScheduled(string $actorPlayerId, string $requestId, string $appointmentId): void
    {
        DB::transaction(function () use ($actorPlayerId, $requestId, $appointmentId): void {
            [$request, $plan, $event, $actor] = $this->writeState->managerRequest($actorPlayerId, $requestId);
            $appointment = KingPerkAppointment::query()
                ->whereKey($appointmentId)
                ->where('plan_id', $plan->id)
                ->where('assigned_player_id', $request->player_id)
                ->sharedLock()
                ->firstOrFail();

            if ($request->status !== KingPerkRequestStatus::Submitted) {
                throw ValidationException::withMessages(['request' => 'Only a submitted appointment request can be scheduled.']);
            }

            $request->forceFill([
                'status' => KingPerkRequestStatus::Scheduled,
                'scheduled_appointment_id' => $appointment->id,
                'reviewed_by_player_id' => $actor->playerId,
                'reviewed_at' => now(),
            ])->save();
            $this->record('king_perks.request_scheduled', $actor, $request, $event, [
                'appointment_id' => (string) $appointment->id,
            ]);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function record(
        string $name,
        PlayerReference $actor,
        KingPerkRequest $subject,
        Event $event,
        array $metadata = [],
    ): void {
        $metadata = [
            'event_id' => (string) $event->id,
            'kingdom_id' => (string) $event->kingdom_id,
            'actor_player_id' => $actor->playerId,
            'request_player_id' => (string) $subject->player_id,
        ] + $metadata;

        $this->audit->record($name, $actor, $subject, null, $metadata);
        $this->outbox->record($name, null, $subject, $metadata, partitionKey: 'kingdom:'.$event->kingdom_id);
    }
}
