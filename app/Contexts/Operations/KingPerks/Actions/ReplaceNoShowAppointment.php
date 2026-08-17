<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Actions;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ReplaceNoShowAppointment
{
    public function __construct(private KingPerkScheduler $scheduler) {}

    public function handle(
        Player $actor,
        KingPerkAppointment $original,
        Player $replacementPlayer,
    ): KingPerkAppointment {
        return DB::transaction(function () use ($actor, $original, $replacementPlayer): KingPerkAppointment {
            $current = $this->scheduler->markAppointment(
                $actor,
                $original,
                KingPerkAppointmentStatus::NoShow,
            );
            $plan = KingPerkPlan::query()->whereKey($current->plan_id)->firstOrFail();
            $replacement = $this->scheduler->assignAppointment(
                actor: $actor,
                plan: $plan,
                type: $current->appointmentType(),
                target: $replacementPlayer,
                startsAt: $current->startsAt(),
                notes: 'Live replacement for no-show appointment '.(string) $current->id,
            );

            $now = CarbonImmutable::now('UTC');
            if (! $now->lt($replacement->startsAt()) && $now->lt($replacement->endsAt())) {
                return $this->scheduler->markAppointmentActive($actor, $replacement);
            }

            return $replacement;
        });
    }
}
