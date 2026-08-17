<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\MembershipStatisticsQuery;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentMetricsQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionDataQualityFlag;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Models\ContributionReportRun;
use App\Contexts\Intelligence\Contributions\Models\ContributionReportSchedule;
use App\Contexts\Intelligence\Contributions\Services\ContributionPeriodResolver;
use Illuminate\Support\Collection;
use LogicException;

final readonly class ContributionReportingQuery
{
    public function __construct(
        private ContributionPeriodResolver $periods,
        private AllianceReferenceQuery $alliances,
        private PlayerReferenceQuery $players,
        private MembershipStatisticsQuery $membershipStatistics,
        private RecruitmentMetricsQuery $recruitmentMetrics,
    ) {}

    /** @return array<string, mixed> */
    public function memberDashboard(string $allianceId, string $playerId): array
    {
        $alliance = $this->alliances->require($allianceId);
        $categories = ContributionCategory::query()
            ->where('alliance_id', $allianceId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $progress = [];
        foreach ($categories as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $approved = (float) ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $playerId)
                ->where('category_id', $category->id)
                ->where('status', ContributionRecordStatus::Approved->value)
                ->whereDate('period_start', $period['start']->toDateString())
                ->whereDate('period_end', $period['end']->toDateString())
                ->sum('value');

            $goal = $category->goal_value === null ? null : (float) $category->goal_value;
            $progress[] = [
                'categoryId' => $category->id,
                'name' => $category->name,
                'unit' => $category->unit,
                'period' => $category->period->value,
                'periodStart' => $period['start']->toDateString(),
                'periodEnd' => $period['end']->toDateString(),
                'approved' => $approved,
                'goal' => $goal,
                'progress' => $goal !== null && $goal > 0 ? min(1, $approved / $goal) : null,
                'selfReportAllowed' => (bool) $category->allow_self_report,
                'evidenceRequired' => (bool) $category->evidence_required,
                'dataClass' => $category->data_class->value,
                'calculationKey' => $category->calculation_key,
                'calculationVersion' => $category->calculation_version,
                'calculationDescription' => $category->calculation_description,
            ];
        }

        $historyRecords = ContributionRecord::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->with('category')
            ->latest('recorded_at')
            ->limit(100)
            ->get();
        $player = $this->players->find($playerId);

        return [
            'progress' => $progress,
            'history' => $historyRecords->map(fn (ContributionRecord $record): array => $this->record($record, $player))->all(),
            'leaderboards' => $this->leaderboards($allianceId, $alliance->timezone, $categories),
        ];
    }

    /** @return array<string, mixed> */
    public function managementDashboard(string $allianceId): array
    {
        $alliance = $this->alliances->require($allianceId);
        $categories = ContributionCategory::query()->where('alliance_id', $allianceId)->orderBy('name')->get();
        $memberFacts = $this->membershipStatistics->activeMemberFacts($allianceId);
        $membershipStats = $this->membershipStatistics->contributionStatistics($allianceId);
        $recruitmentStats = $this->recruitmentMetrics->contributionStatistics($allianceId);
        $playerRefs = $this->players->byIds(array_values(array_unique(array_map(
            static fn (array $row): string => $row['playerId'],
            $memberFacts,
        ))));

        $categorySummaries = [];
        foreach ($categories as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $approved = (float) ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->where('category_id', $category->id)
                ->where('status', ContributionRecordStatus::Approved->value)
                ->whereDate('period_start', $period['start']->toDateString())
                ->whereDate('period_end', $period['end']->toDateString())
                ->sum('value');

            $categorySummaries[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'unit' => $category->unit,
                'period' => $category->period->value,
                'periodStart' => $category->period_start?->toDateString(),
                'periodEnd' => $category->period_end?->toDateString(),
                'goal' => $category->goal_value === null ? null : (float) $category->goal_value,
                'approvedTotal' => $approved,
                'evidenceRequired' => (bool) $category->evidence_required,
                'selfReportAllowed' => (bool) $category->allow_self_report,
                'leaderboardEnabled' => (bool) $category->leaderboard_enabled,
                'dataClass' => $category->data_class->value,
                'calculationKey' => $category->calculation_key,
                'calculationVersion' => $category->calculation_version,
                'calculationDescription' => $category->calculation_description,
                'active' => (bool) $category->is_active,
            ];
        }

        $pending = ContributionRecord::query()->where('alliance_id', $allianceId)->where('status', ContributionRecordStatus::Pending->value)->with('category')->oldest('recorded_at')->limit(100)->get();
        $recent = ContributionRecord::query()->where('alliance_id', $allianceId)->with('category')->latest('recorded_at')->limit(100)->get();
        $recordPlayerIds = $pending->concat($recent)->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all();
        $recordPlayers = $this->players->byIds($recordPlayerIds);

        return [
            'metrics' => [
                'activeMembers' => $membershipStats['active'],
                'joinedLast30Days' => $membershipStats['joined_last_30_days'],
                'leftLast30Days' => $membershipStats['left_last_30_days'],
                'recruitmentTotal' => $recruitmentStats['total'],
                'recruitmentJoined' => $recruitmentStats['joined'],
                'pendingContributionApprovals' => ContributionRecord::query()->where('alliance_id', $allianceId)->where('status', ContributionRecordStatus::Pending->value)->count(),
                'openDataQualityFlags' => ContributionDataQualityFlag::query()->where('alliance_id', $allianceId)->where('status', 'open')->count(),
            ],
            'categories' => $categorySummaries,
            'members' => array_map(static function (array $row) use ($playerRefs): array {
                $player = $playerRefs[$row['playerId']] ?? null;
                return [
                    'playerId' => $row['playerId'],
                    'name' => $player?->currentName,
                    'rank' => $row['rankObservedAtRead'],
                    'claimed' => $player?->claimed() ?? false,
                ];
            }, $memberFacts),
            'pendingRecords' => $pending->map(fn (ContributionRecord $record): array => $this->record($record, $recordPlayers[(string) $record->player_id] ?? null))->all(),
            'recentRecords' => $recent->map(fn (ContributionRecord $record): array => $this->record($record, $recordPlayers[(string) $record->player_id] ?? null))->all(),
            'dataQualityFlags' => ContributionDataQualityFlag::query()
                ->where('alliance_id', $allianceId)->where('status', 'open')
                ->orderByRaw("case when severity = 'error' then 0 else 1 end")
                ->latest('detected_at')->limit(100)->get()
                ->map(static fn (ContributionDataQualityFlag $flag): array => [
                    'id' => $flag->id,
                    'playerId' => $flag->player_id,
                    'categoryId' => $flag->category_id,
                    'recordId' => $flag->record_id,
                    'code' => $flag->code,
                    'severity' => $flag->severity,
                    'message' => $flag->message,
                    'detectedAt' => $flag->detected_at->toIso8601String(),
                ])->all(),
            'leaderboards' => $this->leaderboards($allianceId, $alliance->timezone, $categories),
            'reportSchedules' => ContributionReportSchedule::query()->where('alliance_id', $allianceId)->orderBy('name')->get()
                ->map(static fn (ContributionReportSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'recipientPlayerId' => $schedule->recipient_player_id,
                    'cadence' => $schedule->cadence,
                    'timezone' => $schedule->timezone,
                    'nextDueAt' => $schedule->next_due_at->toIso8601String(),
                    'reportVersion' => $schedule->report_version,
                    'enabled' => (bool) $schedule->is_enabled,
                    'lastQueuedAt' => $schedule->last_queued_at?->toIso8601String(),
                ])->all(),
            'recentReportRuns' => ContributionReportRun::query()->where('alliance_id', $allianceId)->latest('created_at')->limit(25)->get()
                ->map(static fn (ContributionReportRun $run): array => [
                    'id' => $run->id,
                    'format' => $run->format,
                    'status' => $run->status,
                    'reportVersion' => $run->report_version,
                    'rowCount' => $run->row_count,
                    'checksum' => $run->checksum,
                    'queuedAt' => $run->queued_at?->toIso8601String(),
                    'completedAt' => $run->completed_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /** @return list<array<string, scalar|null>> */
    public function reportRows(string $allianceId): array
    {
        $records = ContributionRecord::query()
            ->where('alliance_id', $allianceId)
            ->with('category')
            ->orderBy('period_start')->orderBy('category_id')->orderBy('player_id')->orderBy('recorded_at')
            ->get();
        $players = $this->players->byIds($records->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());

        return array_values($records->map(function (ContributionRecord $record) use ($players): array {
            $category = $record->category;
            $player = $players[(string) $record->player_id] ?? null;
            if (! $category instanceof ContributionCategory || ! $player instanceof PlayerReference) {
                throw new LogicException('Contribution record must reference a category and Player identity.');
            }

            return [
                'record_id' => $record->id,
                'player_id' => $player->playerId,
                'player' => $player->currentName,
                'category' => $category->name,
                'unit' => $category->unit,
                'value' => (float) $record->value,
                'period_start' => $record->period_start->toDateString(),
                'period_end' => $record->period_end->toDateString(),
                'status' => $record->status->value,
                'source' => $record->source->value,
                'data_class' => $record->data_class->value,
                'evidence' => $record->evidence,
                'calculation_key' => $record->calculation_key,
                'calculation_version' => $record->calculation_version,
                'correction_of_record_id' => $record->correction_of_record_id,
                'recorded_at' => $record->recorded_at->toIso8601String(),
                'approved_at' => $record->approved_at?->toIso8601String(),
                'reversed_at' => $record->reversed_at?->toIso8601String(),
                'reversal_reason' => $record->reversal_reason,
                'correction_reason' => $record->correction_reason,
            ];
        })->all());
    }

    /** @param Collection<int, ContributionCategory> $categories
     * @return list<array<string, mixed>>
     */
    private function leaderboards(string $allianceId, string $timezone, Collection $categories): array
    {
        $boards = [];
        foreach ($categories->where('leaderboard_enabled', true)->where('is_active', true) as $category) {
            $period = $this->periods->current($category, $timezone);
            $records = ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->where('category_id', $category->id)
                ->where('status', ContributionRecordStatus::Approved->value)
                ->whereDate('period_start', $period['start']->toDateString())
                ->whereDate('period_end', $period['end']->toDateString())
                ->get();
            $players = $this->players->byIds($records->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());

            $totals = [];
            foreach ($records as $record) {
                $key = (string) $record->player_id;
                $player = $players[$key] ?? null;
                if (! $player instanceof PlayerReference) {
                    continue;
                }
                $totals[$key] ??= ['playerId' => $key, 'name' => $player->currentName, 'value' => 0.0];
                $totals[$key]['value'] += (float) $record->value;
            }
            usort($totals, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);

            $boards[] = [
                'categoryId' => $category->id,
                'name' => $category->name,
                'unit' => $category->unit,
                'periodStart' => $period['start']->toDateString(),
                'periodEnd' => $period['end']->toDateString(),
                'calculationKey' => $category->calculation_key,
                'calculationVersion' => $category->calculation_version,
                'calculationDescription' => $category->calculation_description ?? 'Approved records are summed for the selected category and period.',
                'entries' => array_values($totals),
            ];
        }

        return $boards;
    }

    /** @return array<string, mixed> */
    private function record(ContributionRecord $record, ?PlayerReference $player): array
    {
        $category = $record->category;

        return [
            'id' => $record->id,
            'playerId' => $record->player_id,
            'playerName' => $player?->currentName,
            'categoryId' => $record->category_id,
            'categoryName' => $category?->name,
            'unit' => $category?->unit,
            'value' => (float) $record->value,
            'source' => $record->source->value,
            'dataClass' => $record->data_class->value,
            'status' => $record->status->value,
            'evidence' => $record->evidence,
            'periodStart' => $record->period_start->toDateString(),
            'periodEnd' => $record->period_end->toDateString(),
            'recordedAt' => $record->recorded_at->toIso8601String(),
            'approvedAt' => $record->approved_at?->toIso8601String(),
            'reversedAt' => $record->reversed_at?->toIso8601String(),
            'reversalReason' => $record->reversal_reason,
            'correctionReason' => $record->correction_reason,
            'correctionOfRecordId' => $record->correction_of_record_id,
            'calculationKey' => $record->calculation_key,
            'calculationVersion' => $record->calculation_version,
            'calculationInputs' => $record->calculation_inputs,
        ];
    }
}
