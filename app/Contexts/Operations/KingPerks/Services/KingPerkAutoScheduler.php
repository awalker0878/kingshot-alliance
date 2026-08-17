<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingPerkRequestStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPositionBlock;
use App\Contexts\Operations\KingPerks\Models\KingPerkRequest;
use App\Contexts\Operations\KingPerks\Services\Internal\KingPerkWriteState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KingPerkAutoScheduler
{
    public function __construct(
        private KingPerkWriteState $writeState,
        private PlayerReferenceQuery $players,
        private KingPerkScheduler $scheduler,
        private KingPerkRequestService $requests,
    ) {}

    /**
     * Auto-fill only within one preparation focus. Requests are never compared across categories.
     *
     * @return array{assigned:int,appointment_ids:list<string>,request_ids:list<string>}
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        KingPerkPushCategory $category,
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $limit = 200,
    ): array {
        $plan = DB::transaction(function () use ($actorPlayerId, $planId): array {
            [$current] = $this->writeState->managerPlan($actorPlayerId, $planId);
            if (! in_array($current->status, [KingPerkPlanStatus::Draft, KingPerkPlanStatus::Published, KingPerkPlanStatus::Active], true)) {
                throw ValidationException::withMessages(['plan' => 'This King Perks plan can no longer be scheduled.']);
            }

            return [
                'id' => (string) $current->id,
                'kingdom_id' => (string) $current->kingdom_id,
                'window_starts_at' => CarbonImmutable::instance($current->window_starts_at),
                'window_ends_at' => CarbonImmutable::instance($current->window_ends_at),
            ];
        });

        $start = $from->utc();
        $end = $until->utc();
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages(['until' => 'Auto-schedule end must be after its start.']);
        }
        if ($start->lt($plan['window_starts_at']) || $end->gt($plan['window_ends_at'])) {
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
                if ($this->positionBlocked($plan['id'], $type, $cursor, $slotEnd)) {
                    $cursor = $slotEnd;
                    continue;
                }

                $candidates = KingPerkRequest::query()
                    ->where('plan_id', $plan['id'])
                    ->where('push_category', $category->value)
                    ->where('status', KingPerkRequestStatus::Submitted->value)
                    ->where('availability_starts_at', '<=', $cursor)
                    ->where('availability_ends_at', '>=', $slotEnd)
                    ->where(function ($query) use ($type): void {
                        $query->whereNull('preferred_appointment_type')
                            ->orWhere('preferred_appointment_type', $type->value);
                    })
                    ->orderByDesc('planned_speedup_minutes')
                    ->orderByDesc('planned_resource_amount')
                    ->orderBy('created_at')
                    ->limit(100)
                    ->get();
                $players = $this->players->byIds($candidates->pluck('player_id')->map(static fn ($id): string => (string) $id)->all());

                foreach ($candidates as $request) {
                    $target = $players[(string) $request->player_id] ?? null;
                    if ($target === null || $target->kingdomId !== $plan['kingdom_id']) {
                        continue;
                    }

                    try {
                        $appointmentId = DB::transaction(function () use ($actorPlayerId, $plan, $type, $request, $target, $cursor, $category): string {
                            $appointmentId = $this->scheduler->assignAppointment(
                                actorPlayerId: $actorPlayerId,
                                planId: $plan['id'],
                                type: $type,
                                targetPlayerId: $target->playerId,
                                startsAt: $cursor,
                                notes: sprintf('Auto-scheduled from %s request.', $category->label()),
                            );
                            $this->requests->markScheduled($actorPlayerId, (string) $request->id, $appointmentId);

                            return $appointmentId;
                        });
                    } catch (ValidationException) {
                        continue;
                    }

                    $appointmentIds[] = $appointmentId;
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

    private function positionBlocked(string $planId, KingAppointmentType $type, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if (KingPerkAppointment::query()
            ->where('plan_id', $planId)
            ->where('appointment_type', $type->value)
            ->whereNotIn('status', [KingPerkAppointmentStatus::Cancelled->value, KingPerkAppointmentStatus::NoShow->value])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists()) {
            return true;
        }

        return KingPerkPositionBlock::query()
            ->where('plan_id', $planId)
            ->where('appointment_type', $type->value)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }
}
