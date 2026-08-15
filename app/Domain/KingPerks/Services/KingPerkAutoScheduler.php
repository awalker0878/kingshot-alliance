<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Services;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Domain\KingPerks\Enums\KingPerkPlanStatus;
use App\Domain\KingPerks\Enums\KingPerkPushCategory;
use App\Domain\KingPerks\Enums\KingPerkRequestStatus;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingPerkPositionBlock;
use App\Domain\KingPerks\Models\KingPerkRequest;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkAutoScheduler
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private KingPerkScheduler $scheduler,
        private KingPerkRequestService $requests,
    ) {}

    /**
     * Auto-fill only within one preparation focus. Requests are never compared across categories.
     *
     * @return array{assigned:int,appointment_ids:list<string>,request_ids:list<string>}
     */
    public function handle(
        Player $actor,
        KingPerkPlan $plan,
        KingPerkPushCategory $category,
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $limit = 200,
    ): array {
        $authorizedPlan = DB::transaction(function () use ($actor, $plan): KingPerkPlan {
            $route = KingPerkPlan::query()->select(['id', 'event_id', 'kingdom_id'])->whereKey($plan->id)->firstOrFail();
            $event = Event::query()->whereKey($route->event_id)->firstOrFail();
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::KingPerks);

            if (! $context->target instanceof Kingdom
                || (string) $context->target->id !== (string) $route->kingdom_id) {
                throw new AuthorizationException;
            }

            $current = KingPerkPlan::query()
                ->whereKey($route->id)
                ->where('event_id', $context->event->id)
                ->where('kingdom_id', $context->target->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($current->status, [KingPerkPlanStatus::Draft, KingPerkPlanStatus::Published, KingPerkPlanStatus::Active], true)) {
                throw ValidationException::withMessages(['plan' => 'This King Perks plan can no longer be scheduled.']);
            }

            return $current->refresh();
        });

        $start = $from->utc();
        $end = $until->utc();
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages(['until' => 'Auto-schedule end must be after its start.']);
        }
        if ($start->lt($authorizedPlan->window_starts_at) || $end->gt($authorizedPlan->window_ends_at)) {
            throw ValidationException::withMessages(['from' => 'Auto-schedule window must fit inside Kingdom of Power preparation.']);
        }

        $limit = max(1, min(500, $limit));
        $appointmentIds = [];
        $requestIds = [];

        foreach ($category->preferredAppointments() as $type) {
            $cursor = $start;
            while ($cursor->lt($end) && count($appointmentIds) < $limit) {
                $slotEnd = $cursor->addMinutes($type->durationMinutes());
                if ($slotEnd->gt($end)) {
                    break;
                }

                if ($this->positionBlocked($authorizedPlan, $type, $cursor, $slotEnd)) {
                    $cursor = $slotEnd;

                    continue;
                }

                $candidates = KingPerkRequest::query()
                    ->where('plan_id', $authorizedPlan->id)
                    ->where('push_category', $category->value)
                    ->where('status', KingPerkRequestStatus::Submitted->value)
                    ->where('availability_starts_at', '<=', $cursor)
                    ->where('availability_ends_at', '>=', $slotEnd)
                    ->where(function ($query) use ($type): void {
                        $query->whereNull('preferred_appointment_type')
                            ->orWhere('preferred_appointment_type', $type->value);
                    })
                    ->whereHas('player', static function ($query) use ($authorizedPlan): void {
                        $query->where('current_kingdom_id', $authorizedPlan->kingdom_id);
                    })
                    ->with('player')
                    ->orderByDesc('planned_speedup_minutes')
                    ->orderByDesc('planned_resource_amount')
                    ->orderBy('created_at')
                    ->limit(100)
                    ->get();

                foreach ($candidates as $request) {
                    $target = $request->player;
                    if (! $target instanceof Player) {
                        continue;
                    }

                    try {
                        $appointment = DB::transaction(function () use ($actor, $authorizedPlan, $type, $request, $target, $cursor, $category): KingPerkAppointment {
                            $appointment = $this->scheduler->assignAppointment(
                                actor: $actor,
                                plan: $authorizedPlan,
                                type: $type,
                                target: $target,
                                startsAt: $cursor,
                                notes: sprintf('Auto-scheduled from %s request.', $category->label()),
                            );
                            $this->requests->markScheduled($actor, $request, $appointment);

                            return $appointment;
                        });
                    } catch (ValidationException) {
                        continue;
                    }

                    $appointmentIds[] = (string) $appointment->id;
                    $requestIds[] = (string) $request->id;
                    break;
                }

                $cursor = $slotEnd;
            }

            if (count($appointmentIds) >= $limit) {
                break;
            }
        }

        return [
            'assigned' => count($appointmentIds),
            'appointment_ids' => $appointmentIds,
            'request_ids' => $requestIds,
        ];
    }

    private function positionBlocked(
        KingPerkPlan $plan,
        KingAppointmentType $type,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        $occupied = KingPerkAppointment::query()
            ->where('plan_id', $plan->id)
            ->where('appointment_type', $type->value)
            ->whereNotIn('status', [KingPerkAppointmentStatus::Cancelled->value, KingPerkAppointmentStatus::NoShow->value])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($occupied) {
            return true;
        }

        return KingPerkPositionBlock::query()
            ->where('plan_id', $plan->id)
            ->where('appointment_type', $type->value)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }
}
