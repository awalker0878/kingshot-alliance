<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Queries;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use Illuminate\Support\Carbon;

final class RecruitmentMetricsQuery
{
    /** @return array{total:int, joined:int} */
    public function contributionStatistics(string $allianceId): array
    {
        return [
            'total' => RecruitmentCandidate::query()
                ->where('alliance_id', $allianceId)
                ->whereNull('merged_into_id')
                ->count(),
            'joined' => RecruitmentCandidate::query()
                ->where('alliance_id', $allianceId)
                ->whereNull('merged_into_id')
                ->where('stage', RecruitmentStage::Joined->value)
                ->count(),
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   byStage: array<string, int>,
     *   bySource: array<string, int>,
     *   sourceFunnel: array<string, array{submitted:int, accepted:int, joined:int, acceptedRate:float, joinedRate:float}>,
     *   averageResponseHours: float|null,
     *   acceptedRate: float,
     *   joinedRate: float,
     *   averageStageAgeDays: array<string, float>
     * }
     */
    public function summary(string $allianceId): array
    {
        $candidates = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('merged_into_id')
            ->get();

        $total = $candidates->count();
        $byStage = [];
        $bySource = [];
        /** @var array<string, array{submitted:int, accepted:int, joined:int, acceptedRate:float, joinedRate:float}> $sourceFunnel */
        $sourceFunnel = [];
        $responseSeconds = [];
        $accepted = 0;
        $joined = 0;

        foreach (RecruitmentStage::cases() as $stage) {
            $byStage[$stage->value] = 0;
        }

        foreach ($candidates as $candidate) {
            $byStage[$candidate->stage->value]++;
            $source = trim((string) ($candidate->source ?? ''));
            $sourceKey = $source === '' ? 'unspecified' : $source;
            $bySource[$sourceKey] = ($bySource[$sourceKey] ?? 0) + 1;
            $sourceFunnel[$sourceKey] ??= [
                'submitted' => 0,
                'accepted' => 0,
                'joined' => 0,
                'acceptedRate' => 0.0,
                'joinedRate' => 0.0,
            ];
            $sourceFunnel[$sourceKey]['submitted']++;

            if ($candidate->first_responded_at instanceof Carbon) {
                $responseSeconds[] = max(0, $candidate->submitted_at->diffInSeconds($candidate->first_responded_at));
            }

            if ($candidate->accepted_at instanceof Carbon || $candidate->joined_at instanceof Carbon) {
                $accepted++;
                $sourceFunnel[$sourceKey]['accepted']++;
            }

            if ($candidate->joined_at instanceof Carbon) {
                $joined++;
                $sourceFunnel[$sourceKey]['joined']++;
            }
        }

        ksort($bySource);
        foreach ($sourceFunnel as &$sourceMetrics) {
            $submitted = $sourceMetrics['submitted'];
            $sourceMetrics['acceptedRate'] = $submitted === 0
                ? 0.0
                : round($sourceMetrics['accepted'] / $submitted, 4);
            $sourceMetrics['joinedRate'] = $submitted === 0
                ? 0.0
                : round($sourceMetrics['joined'] / $submitted, 4);
        }
        unset($sourceMetrics);
        ksort($sourceFunnel);

        $latestChanges = RecruitmentStageHistory::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('candidate_id', $candidates->pluck('id'))
            ->selectRaw('candidate_id, MAX(changed_at) AS latest_changed_at')
            ->groupBy('candidate_id')
            ->pluck('latest_changed_at', 'candidate_id');

        /** @var array<string, list<float>> $stageAges */
        $stageAges = [];

        foreach ($candidates as $candidate) {
            $changedAt = $latestChanges->get($candidate->id);
            $stageStarted = is_string($changedAt)
                ? Carbon::parse($changedAt)
                : $candidate->submitted_at;
            $stageAges[$candidate->stage->value][] = max(0.0, $stageStarted->diffInSeconds(now()) / 86400);
        }

        $averageStageAgeDays = [];
        foreach (RecruitmentStage::cases() as $stage) {
            $ages = $stageAges[$stage->value] ?? [];
            $averageStageAgeDays[$stage->value] = $ages === []
                ? 0.0
                : round(array_sum($ages) / count($ages), 2);
        }

        return [
            'total' => $total,
            'byStage' => $byStage,
            'bySource' => $bySource,
            'sourceFunnel' => $sourceFunnel,
            'averageResponseHours' => $responseSeconds === []
                ? null
                : round(array_sum($responseSeconds) / count($responseSeconds) / 3600, 2),
            'acceptedRate' => $total === 0 ? 0.0 : round($accepted / $total, 4),
            'joinedRate' => $total === 0 ? 0.0 : round($joined / $total, 4),
            'averageStageAgeDays' => $averageStageAgeDays,
        ];
    }
}
