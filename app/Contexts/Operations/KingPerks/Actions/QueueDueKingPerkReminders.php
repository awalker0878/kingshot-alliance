<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueKingPerkReminders
{
    public function __construct(
        private NotificationDeliveryService $deliveries,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $now = CarbonImmutable::now('UTC');
        $queued = 0;

        $appointments = KingPerkAppointment::query()
            ->whereIn('status', [KingPerkAppointmentStatus::Scheduled->value, KingPerkAppointmentStatus::Confirmed->value])
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->addDay())
            ->with(['plan', 'assignedPlayer'])
            ->orderBy('starts_at')
            ->limit(1000)
            ->get();

        foreach ($appointments as $appointment) {
            $playerReminders = [
                [KingPerkReminderKind::Appointment24Hours, 1440],
                [KingPerkReminderKind::Appointment1Hour, 60],
                [KingPerkReminderKind::Appointment10Minutes, 10],
            ];

            foreach ($playerReminders as [$kind, $minutes]) {
                $dueAt = CarbonImmutable::instance($appointment->starts_at)->utc()->subMinutes($minutes);
                if ($dueAt->greaterThan($now)) {
                    continue;
                }

                $recipient = $appointment->assignedPlayer;
                if ($recipient instanceof Player
                    && $this->eligiblePlayer($recipient, $appointment->plan)
                    && $this->queue($appointment->plan, $recipient, $kind, $dueAt, $appointment, null)) {
                    $queued++;
                    if ($queued >= $limit) {
                        return $queued;
                    }
                }
            }

            if ($appointment->status === KingPerkAppointmentStatus::Scheduled) {
                $dueAt = CarbonImmutable::instance($appointment->starts_at)->utc()->subMinutes(10);
                if (! $dueAt->greaterThan($now)) {
                    foreach ($this->managers($appointment->plan) as $manager) {
                        if ($this->queue(
                            $appointment->plan,
                            $manager,
                            KingPerkReminderKind::AppointmentUnconfirmed10Minutes,
                            $dueAt,
                            $appointment,
                            null,
                        )) {
                            $queued++;
                            if ($queued >= $limit) {
                                return $queued;
                            }
                        }
                    }
                }
            }
        }

        $skills = KingSkillPlan::query()
            ->whereIn('status', [KingSkillStatus::Planned->value, KingSkillStatus::ScheduledInGame->value])
            ->where('planned_activation_at', '>', $now)
            ->where('planned_activation_at', '<=', $now->addDays(3))
            ->with('plan')
            ->orderBy('planned_activation_at')
            ->limit(500)
            ->get();

        foreach ($skills as $skill) {
            $due = [];
            if ($skill->status === KingSkillStatus::Planned) {
                $due[] = [
                    KingPerkReminderKind::SkillSchedulingAvailable,
                    CarbonImmutable::instance($skill->planned_activation_at)
                        ->utc()
                        ->subMinutes($skill->skill_key->advanceSchedulingMinutes()),
                ];
            }
            $due[] = [
                KingPerkReminderKind::Skill1Hour,
                CarbonImmutable::instance($skill->planned_activation_at)->utc()->subHour(),
            ];

            foreach ($due as [$kind, $dueAt]) {
                if ($dueAt->greaterThan($now)) {
                    continue;
                }
                foreach ($this->managers($skill->plan) as $manager) {
                    if ($this->queue($skill->plan, $manager, $kind, $dueAt, null, $skill)) {
                        $queued++;
                        if ($queued >= $limit) {
                            return $queued;
                        }
                    }
                }
            }
        }

        return $queued;
    }

    /** @return Collection<int, Player> */
    private function managers(KingPerkPlan $plan): Collection
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $plan->kingdom_id)
            ->whereHas('role.permissions', static function ($query): void {
                $query->where('permissions.key', OperationsPermission::EventKingdomManage->value);
            })
            ->with('player')
            ->get()
            ->map(static fn (KingdomRoleAssignment $assignment): Player => $assignment->player)
            ->filter(fn (Player $player): bool => $this->eligiblePlayer($player, $plan))
            ->unique(static fn (Player $player): string => (string) $player->id)
            ->values();
    }

    private function eligiblePlayer(Player $player, KingPerkPlan $plan): bool
    {
        return $player->user_id !== null
            && (string) $player->current_kingdom_id === (string) $plan->kingdom_id;
    }

    private function queue(
        KingPerkPlan $plan,
        Player $recipient,
        KingPerkReminderKind $kind,
        CarbonImmutable $dueAt,
        ?KingPerkAppointment $appointment,
        ?KingSkillPlan $skill,
    ): bool {
        return DB::transaction(function () use ($plan, $recipient, $kind, $dueAt, $appointment, $skill): bool {
            $currentPlan = KingPerkPlan::query()->whereKey($plan->id)->sharedLock()->first();
            $currentPlayer = Player::query()->whereKey($recipient->id)->sharedLock()->first();
            if (! $currentPlan instanceof KingPerkPlan
                || ! $currentPlayer instanceof Player
                || ! $this->eligiblePlayer($currentPlayer, $currentPlan)) {
                return false;
            }

            $source = $appointment instanceof KingPerkAppointment ? $appointment : $skill;
            if ($source === null) {
                return false;
            }

            if ($appointment instanceof KingPerkAppointment) {
                $currentAppointment = KingPerkAppointment::query()
                    ->whereKey($appointment->id)
                    ->where('plan_id', $currentPlan->id)
                    ->sharedLock()
                    ->first();
                if (! $currentAppointment instanceof KingPerkAppointment
                    || ! in_array($currentAppointment->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                    return false;
                }
            }

            if ($skill instanceof KingSkillPlan) {
                $currentSkill = KingSkillPlan::query()
                    ->whereKey($skill->id)
                    ->where('plan_id', $currentPlan->id)
                    ->sharedLock()
                    ->first();
                if (! $currentSkill instanceof KingSkillPlan
                    || ! in_array($currentSkill->status, [KingSkillStatus::Planned, KingSkillStatus::ScheduledInGame], true)) {
                    return false;
                }
            }

            $notificationType = 'king_perks.reminder';
            $channel = 'in_app';
            if (! $this->deliveries->isEnabled(
                (int) $currentPlayer->user_id,
                (string) $currentPlayer->id,
                $notificationType,
                $channel,
            )) {
                return false;
            }

            $key = hash('sha256', implode(':', [
                'king-perk-reminder',
                $kind->value,
                (string) $source->id,
                (string) $currentPlayer->id,
            ]));

            $delivery = $this->deliveries->queue(
                notificationType: $notificationType,
                recipientUserId: (int) $currentPlayer->user_id,
                playerId: (string) $currentPlayer->id,
                channel: $channel,
                dueAt: $dueAt,
                idempotencyKey: $key,
                subjectType: $appointment instanceof KingPerkAppointment ? 'king_perk_appointment' : 'king_skill_plan',
                subjectId: (string) $source->id,
                metadata: [
                    'plan_id' => (string) $currentPlan->id,
                    'kind' => $kind->value,
                ],
            );

            if (! $delivery->wasRecentlyCreated) {
                return false;
            }

            $payload = [
                'delivery_id' => (string) $delivery->id,
                'plan_id' => (string) $currentPlan->id,
                'appointment_id' => $appointment?->id,
                'skill_plan_id' => $skill?->id,
                'kind' => $kind->value,
                'recipient_user_id' => (int) $currentPlayer->user_id,
                'player_id' => (string) $currentPlayer->id,
                'channel' => $channel,
                'due_at' => $dueAt->toIso8601String(),
                'origin' => 'system',
            ];

            $this->outbox->record(
                'king_perks.reminder.requested',
                null,
                $delivery,
                $payload,
                idempotencyKey: 'king_perks.reminder.requested:'.$delivery->id,
                partitionKey: 'kingdom:'.$currentPlan->kingdom_id,
            );

            return true;
        });
    }
}
