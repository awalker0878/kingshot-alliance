<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateContributionCategory
{
    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
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
        if ($slug === '') {
            throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
        }

        return DB::transaction(function () use ($actor, $alliance, $name, $slug, $unit, $period, $dataClass, $goalValue, $evidenceRequired, $allowSelfReport, $leaderboardEnabled, $description, $periodStart, $periodEnd, $calculationKey, $calculationVersion, $calculationDescription): ContributionCategory {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::ContributionManage);

            if (ContributionCategory::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('slug', $slug)
                ->exists()) {
                throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
            }

            try {
                $category = ContributionCategory::query()->create([
                    'alliance_id' => $context->alliance->id,
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
                    'created_by_player_id' => $context->actor->id,
                    'updated_by_player_id' => $context->actor->id,
                ]);
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23505') {
                    throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
                }

                throw $exception;
            }

            $this->audit->record('contribution.category.created', $context->actor, $category, $context->alliance, [
                'data_class' => $dataClass->value,
                'period' => $period->value,
                'calculation_version' => $calculationVersion,
            ]);
            $this->outbox->record('contribution.category.created', $context->alliance->id, $category, [
                'category_id' => $category->id,
            ]);

            return $category;
        });
    }
}
