<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
use Illuminate\Support\Carbon;

final class RecruitmentMetricsQuery
{
    /**
     * @return array{
     *   total: int,
     *   byStage: array<string, int>,
     *   bySource: array<string, int>,
     *   averageResponseHours: float|null,
     *   acceptedRate: float,
     *   joinedRate: float,
     *   averageStageAgeDays: array<string, float>
     * }
     */
    public function summary(Alliance $alliance): array
    {
        $candidates = RecruitmentCandidate::query()
            ->where('alliance_id', $alliance->id)
            ->whereNull('merged_into_id')
            ->get();

        $total = $candidates->count();
        $byStage = [];
        $bySource = [];
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

            if ($candidate->first_responded_at instanceof Carbon) {
                $responseSeconds[] = max(0, $candidate->submitted_at->diffInSeconds($candidate->first_responded_at));
            }

            if ($candidate->accepted_at instanceof Carbon || $candidate->joined_at instanceof Carbon) {
                $accepted++;
            }

            if ($candidate->joined_at instanceof Carbon) {
                $joined++;
            }
        }

        ksort($bySource);

        $latestChanges = RecruitmentStageHistory::query()
            ->where('alliance_id', $alliance->id)
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
            'averageResponseHours' => $responseSeconds === []
                ? null
                : round(array_sum($responseSeconds) / count($responseSeconds) / 3600, 2),
            'acceptedRate' => $total === 0 ? 0.0 : round($accepted / $total, 4),
            'joinedRate' => $total === 0 ? 0.0 : round($joined / $total, 4),
            'averageStageAgeDays' => $averageStageAgeDays,
        ];
    }
}
