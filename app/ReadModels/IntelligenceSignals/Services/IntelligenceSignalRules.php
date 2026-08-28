<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Services;

final class IntelligenceSignalRules
{
    public function ruleVersion(): string
    {
        return (string) config('intelligence.change_detection.rule_version', '1');
    }

    public function alliancePowerAbsolute(): float
    {
        return (float) config('intelligence.change_detection.alliance_power_absolute', 100_000_000);
    }

    public function alliancePowerPercent(): float
    {
        return (float) config('intelligence.change_detection.alliance_power_percent', 5.0);
    }

    public function memberCountAbsolute(): int
    {
        return max(1, (int) config('intelligence.change_detection.member_count_absolute', 3));
    }

    public function allianceObservationStaleDays(): int
    {
        return max(1, (int) config('intelligence.change_detection.alliance_observation_stale_days', 30));
    }

    public function progressionObservationStaleDays(): int
    {
        return max(1, (int) config(
            'intelligence.change_detection.progression_observation_stale_days',
            config('intelligence.progression.observation_stale_after_days', 30),
        ));
    }

    public function transferExpiringDays(): int
    {
        return max(1, (int) config('intelligence.change_detection.transfer_expiring_days', 7));
    }

    public function bearHuntMinimumRuns(): int
    {
        return max(3, (int) config('intelligence.change_detection.bear_hunt_minimum_runs', 3));
    }

    public function recentDays(): int
    {
        return max(1, (int) config('intelligence.change_detection.recent_days', 45));
    }

    public function maxSignals(): int
    {
        return max(1, min(100, (int) config('intelligence.change_detection.max_signals', 20)));
    }
}
