<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Queries;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;

final readonly class KingPerkNotificationEligibilityQuery
{
    public function __construct(private KingdomOperationsAuthorization $authorization) {}

    public function allows(NotificationSource $source, PlayerReference $player): bool
    {
        $planId = $source->metadataString('plan_id');
        $kind = KingPerkReminderKind::tryFrom($source->metadataString('kind') ?? '');
        if ($planId === null || $kind === null || $source->subjectId === null) {
            return false;
        }
        $plan = KingPerkPlan::query()->whereKey($planId)->where('kingdom_id', $player->kingdomId)
            ->where('status', '!=', KingPerkPlanStatus::Closed->value)->first();
        if (! $plan instanceof KingPerkPlan) {
            return false;
        }
        if ($source->subjectType === 'king_perk_appointment' && in_array($kind, [
            KingPerkReminderKind::Appointment24Hours, KingPerkReminderKind::Appointment1Hour,
            KingPerkReminderKind::Appointment10Minutes, KingPerkReminderKind::AppointmentUnconfirmed10Minutes,
        ], true)) {
            $appointment = KingPerkAppointment::query()->whereKey($source->subjectId)->where('plan_id', $planId)
                ->whereIn('status', [KingPerkAppointmentStatus::Scheduled->value, KingPerkAppointmentStatus::Confirmed->value])->first();
            if (! $appointment instanceof KingPerkAppointment) {
                return false;
            }

            return $kind === KingPerkReminderKind::AppointmentUnconfirmed10Minutes
                ? $appointment->status === KingPerkAppointmentStatus::Scheduled
                    && $this->authorization->allows($player->playerId, $player->kingdomId, OperationsPermission::EventKingdomManage)
                : (string) $appointment->assigned_player_id === $player->playerId;
        }
        if ($source->subjectType === 'king_skill_plan' && in_array($kind, [
            KingPerkReminderKind::SkillSchedulingAvailable, KingPerkReminderKind::Skill1Hour,
        ], true)) {
            return $this->authorization->allows($player->playerId, $player->kingdomId, OperationsPermission::EventKingdomManage)
                && KingSkillPlan::query()->whereKey($source->subjectId)->where('plan_id', $planId)
                    ->whereIn('status', [KingSkillStatus::Planned->value, KingSkillStatus::ScheduledInGame->value])->exists();
        }

        return false;
    }
}
