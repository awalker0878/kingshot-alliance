<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateContributionCategory
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        string $unit,
        ContributionPeriod $period,
        ContributionDataClass $dataClass,
        ?float $goalValue = null,
        bool $evidenceRequired = false,
        bool $allowSelfReport = false,
        bool $leaderboardEnabled = true,
        ?string $description = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?string $calculationKey = null,
        ?string $calculationVersion = null,
        ?string $calculationDescription = null,
    ): ContributionCategory {
        if (in_array($period, [ContributionPeriod::Season, ContributionPeriod::Custom], true)
            && ($periodStart === null || $periodEnd === null)) {
            throw new InvalidArgumentException('Season and custom periods require explicit dates.');
        }

        if ($dataClass === ContributionDataClass::CalculatedMetric
            && ($calculationKey === null || $calculationVersion === null || $calculationDescription === null)) {
            throw new InvalidArgumentException('Calculated contribution categories require a key, version, and explanation.');
        }

        $slug = Str::slug($name);

        if ($slug === '' || ContributionCategory::query()
            ->where('alliance_id', $alliance->id)
            ->where('slug', $slug)
            ->exists()) {
            throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $name,
            $slug,
            $unit,
            $period,
            $dataClass,
            $goalValue,
            $evidenceRequired,
            $allowSelfReport,
            $leaderboardEnabled,
            $description,
            $periodStart,
            $periodEnd,
            $calculationKey,
            $calculationVersion,
            $calculationDescription,
        ): ContributionCategory {
            $category = ContributionCategory::query()->create([
                'alliance_id' => $alliance->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'unit' => $unit,
                'period' => $period,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'goal_value' => $goalValue,
                'evidence_required' => $evidenceRequired,
                'allow_self_report' => $allowSelfReport,
                'leaderboard_enabled' => $leaderboardEnabled,
                'data_class' => $dataClass,
                'calculation_key' => $calculationKey,
                'calculation_version' => $calculationVersion,
                'calculation_description' => $calculationDescription,
                'is_active' => true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('contribution.category.created', $actor, $category, $alliance, [
                'data_class' => $dataClass->value,
                'period' => $period->value,
                'calculation_version' => $calculationVersion,
            ]);
            $this->outbox->record('contribution.category.created', $alliance->id, $category, [
                'category_id' => $category->id,
            ]);

            return $category;
        });
    }
}
