<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueKingPerkReminders
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
        private KingdomOperationsAuthorization $authorization,
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
            ->with('plan')
            ->orderBy('starts_at')
            ->limit(1000)
            ->get();

        foreach ($appointments as $appointment) {
            $recipient = $this->players->find((string) $appointment->assigned_player_id);
            if ($recipient instanceof PlayerReference) {
                foreach ([
                    [KingPerkReminderKind::Appointment24Hours, 1440],
                    [KingPerkReminderKind::Appointment1Hour, 60],
                    [KingPerkReminderKind::Appointment10Minutes, 10],
                ] as [$kind, $minutes]) {
                    $dueAt = CarbonImmutable::instance($appointment->starts_at)->utc()->subMinutes($minutes);
                    if (! $dueAt->greaterThan($now)
                        && $this->queue((string) $appointment->plan_id, $recipient, $kind, $dueAt, (string) $appointment->id, null, false)) {
                        $queued++;
                        if ($queued >= $limit) {
                            return $queued;
                        }
                    }
                }
            }

            if ($appointment->status === KingPerkAppointmentStatus::Scheduled) {
                $dueAt = CarbonImmutable::instance($appointment->starts_at)->utc()->subMinutes(10);
                if (! $dueAt->greaterThan($now)) {
                    foreach ($this->managers($appointment->plan) as $manager) {
                        if ($this->queue(
                            (string) $appointment->plan_id,
                            $manager,
                            KingPerkReminderKind::AppointmentUnconfirmed10Minutes,
                            $dueAt,
                            (string) $appointment->id,
                            null,
                            true,
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
                    CarbonImmutable::instance($skill->planned_activation_at)->utc()->subMinutes($skill->skill_key->advanceSchedulingMinutes()),
                ];
            }
            $due[] = [KingPerkReminderKind::Skill1Hour, CarbonImmutable::instance($skill->planned_activation_at)->utc()->subHour()];

            foreach ($due as [$kind, $dueAt]) {
                if ($dueAt->greaterThan($now)) {
                    continue;
                }
                foreach ($this->managers($skill->plan) as $manager) {
                    if ($this->queue((string) $skill->plan_id, $manager, $kind, $dueAt, null, (string) $skill->id, true)) {
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

    /** @return list<PlayerReference> */
    private function managers(KingPerkPlan $plan): array
    {
        $playerIds = $this->kingdomAuthority->playerIdsWithPermission(
            (string) $plan->kingdom_id,
            OperationsPermission::EventKingdomManage->value,
        );
        $players = $this->players->byIds($playerIds);

        return array_values(array_filter(
            $players,
            fn (PlayerReference $player): bool => $this->eligiblePlayer($player, $plan),
        ));
    }

    private function eligiblePlayer(PlayerReference $player, KingPerkPlan $plan): bool
    {
        return $player->claimed() && $player->kingdomId === (string) $plan->kingdom_id;
    }

    private function queue(
        string $planId,
        PlayerReference $recipient,
        KingPerkReminderKind $kind,
        CarbonImmutable $dueAt,
        ?string $appointmentId,
        ?string $skillId,
        bool $requiresManagerAuthority,
    ): bool {
        return DB::transaction(function () use ($planId, $recipient, $kind, $dueAt, $appointmentId, $skillId, $requiresManagerAuthority): bool {
            $plan = KingPerkPlan::query()->whereKey($planId)->sharedLock()->first();
            if (! $plan instanceof KingPerkPlan) {
                return false;
            }

            $currentPlayer = $this->players->lockCurrent($recipient->playerId);
            if (! $this->eligiblePlayer($currentPlayer, $plan) || $currentPlayer->userId === null) {
                return false;
            }
            if ($requiresManagerAuthority) {
                $facts = $this->kingdomAuthority->lockCurrent($currentPlayer->playerId, (string) $plan->kingdom_id);
                if ($facts === null || ! $this->authorization->allowsFacts($facts, OperationsPermission::EventKingdomManage)) {
                    return false;
                }
            }

            $source = null;
            $subjectType = null;
            if ($appointmentId !== null) {
                $source = KingPerkAppointment::query()
                    ->whereKey($appointmentId)
                    ->where('plan_id', $plan->id)
                    ->sharedLock()
                    ->first();
                if (! $source instanceof KingPerkAppointment
                    || ! in_array($source->status, [KingPerkAppointmentStatus::Scheduled, KingPerkAppointmentStatus::Confirmed], true)) {
                    return false;
                }
                $subjectType = 'king_perk_appointment';
            } elseif ($skillId !== null) {
                $source = KingSkillPlan::query()
                    ->whereKey($skillId)
                    ->where('plan_id', $plan->id)
                    ->sharedLock()
                    ->first();
                if (! $source instanceof KingSkillPlan
                    || ! in_array($source->status, [KingSkillStatus::Planned, KingSkillStatus::ScheduledInGame], true)) {
                    return false;
                }
                $subjectType = 'king_skill_plan';
            }
            if ($source === null || $subjectType === null) {
                return false;
            }

            $title = $source instanceof KingPerkAppointment
                ? $source->appointment_type->label()
                : $source->skill_key->label();
            $body = match ($kind) {
                KingPerkReminderKind::AppointmentUnconfirmed10Minutes => 'This appointment still needs confirmation.',
                KingPerkReminderKind::Appointment24Hours => 'Your King appointment starts within 24 hours.',
                KingPerkReminderKind::Appointment1Hour => 'Your King appointment starts within one hour.',
                KingPerkReminderKind::Appointment10Minutes => 'Your King appointment starts within 10 minutes.',
                KingPerkReminderKind::SkillSchedulingAvailable => 'This King Skill can now be scheduled in game.',
                KingPerkReminderKind::Skill1Hour => 'This King Skill is planned to activate within one hour.',
            };
            $receipt = $this->deliveries->queue(NotificationIntent::fromScalars(
                notificationType: 'king_perks.reminder',
                recipientUserId: $currentPlayer->userId,
                playerId: $currentPlayer->playerId,
                availableAt: $dueAt,
                idempotencyKey: implode(':', [
                    'king-perk-reminder', $kind->value, (string) $source->id, $currentPlayer->playerId,
                ]),
                title: $title,
                body: $body,
                actionUrl: '/events',
                subjectType: $subjectType,
                subjectId: (string) $source->id,
                metadata: [
                    'plan_id' => (string) $plan->id,
                    'kind' => $kind->value,
                ],
            ));

            $inAppDeliveryId = $receipt->inAppDeliveryId;
            if ($inAppDeliveryId !== null
                && in_array($inAppDeliveryId, $receipt->createdDeliveryIds, true)) {
                $payload = [
                    'delivery_id' => $inAppDeliveryId,
                    'message_id' => $receipt->messageId,
                    'plan_id' => (string) $plan->id,
                    'appointment_id' => $appointmentId,
                    'skill_plan_id' => $skillId,
                    'kind' => $kind->value,
                    'recipient_user_id' => $currentPlayer->userId,
                    'player_id' => $currentPlayer->playerId,
                    'channel' => 'in_app',
                    'due_at' => $dueAt->toIso8601String(),
                    'origin' => 'system',
                ];
                $this->outbox->record(
                    'king_perks.reminder.requested',
                    null,
                    $source,
                    $payload,
                    idempotencyKey: 'king_perks.reminder.requested:'.$inAppDeliveryId,
                    partitionKey: 'kingdom:'.$plan->kingdom_id,
                );
            }

            return $receipt->hasCreatedDeliveries();
        });
    }
}
