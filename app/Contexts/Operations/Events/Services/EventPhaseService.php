<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\EventPhaseStatus;
use App\Contexts\Operations\Events\Models\EventPhase;
use Carbon\CarbonImmutable;

final readonly class EventPhaseService
{
    public function effectiveStatus(EventPhase $phase): EventPhaseStatus
    {
        if ($phase->status === EventPhaseStatus::Cancelled) {
            return EventPhaseStatus::Cancelled;
        }
        if ($phase->status === EventPhaseStatus::Completed) {
            return EventPhaseStatus::Completed;
        }
        if ($phase->starts_at === null || $phase->ends_at === null) {
            return $phase->status;
        }

        $now = CarbonImmutable::now('UTC');
        if ($now->greaterThanOrEqualTo(CarbonImmutable::instance($phase->ends_at)->utc())) {
            return EventPhaseStatus::Completed;
        }
        if ($now->greaterThanOrEqualTo(CarbonImmutable::instance($phase->starts_at)->utc())) {
            return EventPhaseStatus::Active;
        }

        return EventPhaseStatus::Scheduled;
    }
}
