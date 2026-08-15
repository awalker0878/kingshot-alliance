<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionDataQualityFlag;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Models\ContributionReportRun;
use App\Domain\Contributions\Models\ContributionReportSchedule;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use Illuminate\Support\Collection;
use LogicException;

final class ContributionReportingQuery
{
    public function __construct(private readonly ContributionPeriodResolver $periods) {}

    /** @return array<string, mixed> */
    public function memberDashboard(Alliance $alliance, Player $player): array
    {
        $categories = ContributionCategory::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $progress = [];
        foreach ($categories as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $approved = (float) ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $player->id)
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

        $history = ContributionRecord::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->with(['category', 'player'])
            ->latest('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (ContributionRecord $record): array => $this->record($record))
            ->all();

        return [
            'progress' => $progress,
            'history' => $history,
            'leaderboards' => $this->leaderboards($alliance, $categories),
        ];
    }

    /** @return array<string, mixed> */
    public function managementDashboard(Alliance $alliance): array
    {
        $categories = ContributionCategory::query()->where('alliance_id', $alliance->id)->orderBy('name')->get();
        $activeMembers = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('player:id,user_id,current_name')
            ->orderBy('created_at')
            ->get();

        $categorySummaries = [];
        foreach ($categories as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $approved = (float) ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
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

        return [
            'metrics' => [
                'activeMembers' => $activeMembers->count(),
                'joinedLast30Days' => AllianceMembership::query()->where('alliance_id', $alliance->id)->whereNotNull('joined_at')->where('joined_at', '>=', now()->subDays(30))->count(),
                'leftLast30Days' => AllianceMembership::query()->where('alliance_id', $alliance->id)->whereNotNull('left_at')->where('left_at', '>=', now()->subDays(30))->count(),
                'recruitmentTotal' => RecruitmentCandidate::query()->where('alliance_id', $alliance->id)->whereNull('merged_into_id')->count(),
                'recruitmentJoined' => RecruitmentCandidate::query()->where('alliance_id', $alliance->id)->where('stage', RecruitmentStage::Joined->value)->count(),
                'pendingContributionApprovals' => ContributionRecord::query()->where('alliance_id', $alliance->id)->where('status', ContributionRecordStatus::Pending->value)->count(),
                'openDataQualityFlags' => ContributionDataQualityFlag::query()->where('alliance_id', $alliance->id)->where('status', 'open')->count(),
            ],
            'categories' => $categorySummaries,
            'members' => $activeMembers->map(static fn (AllianceMembership $membership): array => [
                'playerId' => (string) $membership->player_id,
                'name' => (string) $membership->player->current_name,
                'rank' => $membership->rank->value,
                'claimed' => $membership->player->user_id !== null,
            ])->all(),
            'pendingRecords' => ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', ContributionRecordStatus::Pending->value)
                ->with(['category', 'player'])
                ->oldest('recorded_at')->limit(100)->get()
                ->map(fn (ContributionRecord $record): array => $this->record($record))->all(),
            'recentRecords' => ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
                ->with(['category', 'player'])
                ->latest('recorded_at')->limit(100)->get()
                ->map(fn (ContributionRecord $record): array => $this->record($record))->all(),
            'dataQualityFlags' => ContributionDataQualityFlag::query()
                ->where('alliance_id', $alliance->id)->where('status', 'open')
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
            'leaderboards' => $this->leaderboards($alliance, $categories),
            'reportSchedules' => ContributionReportSchedule::query()
                ->where('alliance_id', $alliance->id)->orderBy('name')->get()
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
            'recentReportRuns' => ContributionReportRun::query()
                ->where('alliance_id', $alliance->id)->latest('created_at')->limit(25)->get()
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
    public function reportRows(Alliance $alliance): array
    {
        return array_values(ContributionRecord::query()
            ->where('alliance_id', $alliance->id)
            ->with(['category', 'player'])
            ->orderBy('period_start')->orderBy('category_id')->orderBy('player_id')->orderBy('recorded_at')
            ->get()
            ->map(function (ContributionRecord $record): array {
                $category = $record->category;
                $player = $record->player;
                if (! $category instanceof ContributionCategory || ! $player instanceof Player) {
                    throw new LogicException('Contribution record must reference a category and Player.');
                }

                return [
                    'record_id' => $record->id,
                    'player_id' => (string) $player->id,
                    'player' => $player->current_name,
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
    private function leaderboards(Alliance $alliance, Collection $categories): array
    {
        $boards = [];
        foreach ($categories->where('leaderboard_enabled', true)->where('is_active', true) as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $records = ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
                ->where('category_id', $category->id)
                ->where('status', ContributionRecordStatus::Approved->value)
                ->whereDate('period_start', $period['start']->toDateString())
                ->whereDate('period_end', $period['end']->toDateString())
                ->with('player')->get();

            $totals = [];
            foreach ($records as $record) {
                $player = $record->player;
                if (! $player instanceof Player) {
                    continue;
                }
                $key = (string) $record->player_id;
                $totals[$key] ??= ['playerId' => $key, 'name' => $player->current_name, 'value' => 0.0];
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
                'entries' => $totals,
            ];
        }

        return $boards;
    }

    /** @return array<string, mixed> */
    private function record(ContributionRecord $record): array
    {
        $category = $record->category;
        $player = $record->player;

        return [
            'id' => $record->id,
            'playerId' => $record->player_id,
            'playerName' => $player instanceof Player ? $player->current_name : null,
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
