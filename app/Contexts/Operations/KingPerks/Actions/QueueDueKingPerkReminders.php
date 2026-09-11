<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingPerkReminderCursor;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Contexts\Operations\KingPerks\Queries\DueKingPerkReminderQuery;
use App\Contexts\Operations\KingPerks\ValueObjects\DueKingPerkReminder;
use App\Contexts\Operations\KingPerks\ValueObjects\KingPerkReminderSweepResult;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class QueueDueKingPerkReminders
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private KingdomReferenceQuery $kingdoms,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
        private KingdomOperationsAuthorization $authorization,
        private NotificationDeliveryService $deliveries,
        private OutboxRecorder $outbox,
        private DueKingPerkReminderQuery $due,
    ) {}

    public function handle(int $limit = 100): KingPerkReminderSweepResult
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('The reminder sweep owns short per-recipient transactions.');
        }
        $limit = max(1, min(1000, $limit));
        $now = CarbonImmutable::now('UTC');
        $removed = DB::transaction(static function () use ($now): int {
            $expired = KingPerkReminderCursor::query()->where('expires_at', '<', $now)
                ->orderBy('id')->limit(100)->lockForUpdate()->pluck('id');

            return KingPerkReminderCursor::query()->whereIn('id', $expired)->delete();
        });
        $work = $queued = $sources = $recipients = $superseded = 0;

        foreach ($this->due->next($now, $limit) as $candidate) {
            if ($work >= $limit) {
                break;
            }
            $sources++;
            $cursor = KingPerkReminderCursor::query()->firstOrCreate(
                ['kind' => $candidate->kind->value, 'source_id' => $candidate->sourceId],
                ['version' => 0, 'visited_at' => $now, 'expires_at' => $now->addWeek()],
            );
            $pageSize = min(25, $limit - $work);
            $ids = $candidate->kind->requiresManagerAuthority()
                ? $this->kingdomAuthority->playerIdsWithPermissionAfter(
                    $candidate->kingdomId, OperationsPermission::EventKingdomManage->value, $cursor->after_player_id, $pageSize,
                )
                : ($candidate->assignedPlayerId === null ? [] : [$candidate->assignedPlayerId]);
            $version = $cursor->version;
            if ($ids === []) {
                $work++;
                if ($this->advance($cursor->id, $version, $candidate, null, true, $now) === null) {
                    $superseded++;
                }

                continue;
            }
            $last = array_key_last($ids);
            foreach ($ids as $index => $playerId) {
                $work++;
                $recipients++;
                $complete = $index === $last && (count($ids) < $pageSize || ! $candidate->kind->requiresManagerAuthority());
                $created = $this->advance($cursor->id, $version, $candidate, $playerId, $complete, $now);
                if ($created === null) {
                    $superseded++;
                    break;
                }
                $version++;
                $queued += (int) $created;
            }
        }

        return new KingPerkReminderSweepResult($work, $sources, $recipients, $queued, $superseded, $removed);
    }

    /** Null means another invocation advanced or replaced this page's cursor. */
    private function advance(
        string $cursorId,
        int $expectedVersion,
        DueKingPerkReminder $candidate,
        ?string $playerId,
        bool $complete,
        CarbonImmutable $now,
    ): ?bool {
        return DB::transaction(function () use ($cursorId, $expectedVersion, $candidate, $playerId, $complete, $now): ?bool {
            $cursor = KingPerkReminderCursor::query()->whereKey($cursorId)->lockForUpdate()->first();
            if (! $cursor instanceof KingPerkReminderCursor || $cursor->version !== $expectedVersion) {
                return null;
            }
            $created = $playerId !== null && $this->queue($candidate, $playerId);
            $cursor->after_player_id = $complete ? null : $playerId;
            $cursor->version++;
            // Strict progress even during multiple invocations at the same clock value.
            $cursor->visited_at = $now->greaterThan($cursor->visited_at) ? $now : $cursor->visited_at->addMicrosecond();
            $cursor->expires_at = $now->addWeek();
            $cursor->save();

            return $created;
        });
    }

    private function queue(DueKingPerkReminder $candidate, string $playerId): bool
    {
        // These are owner locks, not permission snapshots from the audience query.
        // Never hold one recipient's locks while acquiring the next recipient.
        try {
            $this->kingdoms->lockActiveShared($candidate->kingdomId);
            $currentPlayer = $this->players->lockCurrent($playerId);
        } catch (ModelNotFoundException) {
            return false;
        }
        if (! $currentPlayer->claimed() || $currentPlayer->userId === null || $currentPlayer->kingdomId !== $candidate->kingdomId) {
            return false;
        }
        $kind = $candidate->kind;
        if ($kind->requiresManagerAuthority()) {
            $facts = $this->kingdomAuthority->lockCurrent($playerId, $candidate->kingdomId);
            if ($facts === null || ! $this->authorization->allowsFacts($facts, OperationsPermission::EventKingdomManage)) {
                return false;
            }
        }
        $plan = KingPerkPlan::query()->whereKey($candidate->planId)->where('kingdom_id', $candidate->kingdomId)->sharedLock()->first();
        if (! $plan instanceof KingPerkPlan || $plan->status === KingPerkPlanStatus::Closed) {
            return false;
        }
        $source = $kind->isAppointment()
            ? KingPerkAppointment::query()->whereKey($candidate->sourceId)->where('plan_id', $plan->id)->sharedLock()->first()
            : KingSkillPlan::query()->whereKey($candidate->sourceId)->where('plan_id', $plan->id)->sharedLock()->first();
        if ($source === null || ! in_array($source->status->value, $kind->sourceStatuses(), true)) {
            return false;
        }
        if ($source instanceof KingPerkAppointment && ! $kind->requiresManagerAuthority() && (string) $source->assigned_player_id !== $playerId) {
            return false;
        }
        $now = CarbonImmutable::now('UTC');
        $startsAt = CarbonImmutable::instance($source instanceof KingPerkAppointment ? $source->starts_at : $source->planned_activation_at)->utc();
        $dueAt = $startsAt->subMinutes($kind->leadMinutes($source instanceof KingSkillPlan ? $source->skill_key : null));
        if (! $startsAt->greaterThan($now) || $dueAt->greaterThan($now)) {
            return false;
        }
        $appointmentId = $source instanceof KingPerkAppointment ? (string) $source->id : null;
        $skillId = $source instanceof KingSkillPlan ? (string) $source->id : null;
        $subjectType = $source instanceof KingPerkAppointment ? 'king_perk_appointment' : 'king_skill_plan';

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
    }
}
