<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Support\Carbon;

final class EvidenceDiagnosticsQuery
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        $statusCounts = GameEvidence::query()
            ->selectRaw('lifecycle_status, count(*) as aggregate')
            ->groupBy('lifecycle_status')
            ->pluck('aggregate', 'lifecycle_status')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $classificationAttempts = EvidenceClassificationAttempt::query()->count();
        $classificationFailures = EvidenceClassificationAttempt::query()
            ->where('status', EvidenceAttemptStatus::Failed->value)
            ->count();
        $extractionAttempts = EvidenceExtractionAttempt::query()->count();
        $extractionFailures = EvidenceExtractionAttempt::query()
            ->where('status', EvidenceAttemptStatus::Failed->value)
            ->count();
        $reviewCount = EvidenceReview::query()->count();
        $semanticDuplicates = EvidenceReview::query()
            ->where('status', EvidenceReviewStatus::DuplicateBlocked->value)
            ->count();
        $reviewRows = EvidenceReviewRow::query()->count();
        $correctedRows = EvidenceReviewRow::query()
            ->where(static function ($query): void {
                $query->where('rank_corrected', true)
                    ->orWhere('name_corrected', true)
                    ->orWhere('damage_corrected', true);
            })
            ->count();
        $commitAttempts = EvidenceCommitAttempt::query()->count();
        $commitFailures = EvidenceCommitAttempt::query()
            ->where('status', EvidenceCommitStatus::Failed->value)
            ->count();
        $oldestProcessingAt = GameEvidence::query()
            ->whereIn('lifecycle_status', ['classifying', 'extracting', 'committing'])
            ->min('updated_at');

        return [
            'evidenceByStatus' => $statusCounts,
            'classificationAttempts' => $classificationAttempts,
            'classificationFailures' => $classificationFailures,
            'classificationFailureRate' => $this->rate($classificationFailures, $classificationAttempts),
            'classificationAverageLatencySeconds' => $this->averageLatencySeconds(EvidenceClassificationAttempt::class),
            'extractionAttempts' => $extractionAttempts,
            'extractionFailures' => $extractionFailures,
            'extractionFailureRate' => $this->rate($extractionFailures, $extractionAttempts),
            'extractionAverageLatencySeconds' => $this->averageLatencySeconds(EvidenceExtractionAttempt::class),
            'reviewCount' => $reviewCount,
            'reviewRows' => $reviewRows,
            'correctedRows' => $correctedRows,
            'reviewCorrectionRate' => $this->rate($correctedRows, $reviewRows),
            'semanticDuplicates' => $semanticDuplicates,
            'semanticDuplicateRate' => $this->rate($semanticDuplicates, $reviewCount),
            'exactDuplicateEvents' => AuditEvent::query()->where('event', 'evidence.exact_duplicate_detected')->count(),
            'visualDuplicateEvents' => AuditEvent::query()->where('event', 'evidence.visual_duplicate_detected')->count(),
            'commitAttempts' => $commitAttempts,
            'commitFailures' => $commitFailures,
            'commitFailureRate' => $this->rate($commitFailures, $commitAttempts),
            'retentionFailures' => AuditEvent::query()->where('event', 'evidence.retention_failed')->count(),
            'oldestProcessingAt' => $oldestProcessingAt,
            'oldestProcessingAgeSeconds' => $oldestProcessingAt === null
                ? null
                : (int) Carbon::parse((string) $oldestProcessingAt)->diffInSeconds(now()),
            'retainedBinaryCount' => GameEvidence::query()->whereNotNull('path')->count(),
            'redactedCount' => GameEvidence::query()->whereNotNull('redacted_at')->count(),
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($numerator / $denominator, 4);
    }

    /** @param class-string<EvidenceClassificationAttempt|EvidenceExtractionAttempt> $model */
    private function averageLatencySeconds(string $model): ?float
    {
        $value = $model::query()
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - started_at))) AS average_latency_seconds')
            ->value('average_latency_seconds');

        return $value === null ? null : round((float) $value, 3);
    }
}
