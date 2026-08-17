<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateContributionCategory
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
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
    ): void {
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

        DB::transaction(function () use ($actorPlayerId, $allianceId, $name, $slug, $unit, $period, $dataClass, $goalValue, $evidenceRequired, $allowSelfReport, $leaderboardEnabled, $description, $periodStart, $periodEnd, $calculationKey, $calculationVersion, $calculationDescription): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);

            if (ContributionCategory::query()->where('alliance_id', $allianceId)->where('slug', $slug)->exists()) {
                throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
            }

            try {
                $category = ContributionCategory::query()->create([
                    'alliance_id' => $allianceId,
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
                    'created_by_player_id' => $actor->playerId,
                    'updated_by_player_id' => $actor->playerId,
                ]);
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23505') {
                    throw new InvalidArgumentException('Contribution category name must be unique within the alliance.');
                }
                throw $exception;
            }

            $this->audit->record('contribution.category.created', $actor, $category, $allianceId, [
                'data_class' => $dataClass->value,
                'period' => $period->value,
                'calculation_version' => $calculationVersion,
            ]);
            $this->outbox->record('contribution.category.created', $allianceId, $category, [
                'category_id' => $category->id,
            ]);
        });
    }
}
