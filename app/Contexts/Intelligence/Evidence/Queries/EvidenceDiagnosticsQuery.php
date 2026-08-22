<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;

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

        return [
            'evidenceByStatus' => $statusCounts,
            'classificationFailures' => EvidenceClassificationAttempt::query()
                ->where('status', EvidenceAttemptStatus::Failed->value)
                ->count(),
            'extractionFailures' => EvidenceExtractionAttempt::query()
                ->where('status', EvidenceAttemptStatus::Failed->value)
                ->count(),
            'commitFailures' => EvidenceCommitAttempt::query()
                ->where('status', EvidenceCommitStatus::Failed->value)
                ->count(),
            'oldestProcessingAt' => GameEvidence::query()
                ->whereIn('lifecycle_status', ['classifying', 'extracting', 'committing'])
                ->min('updated_at'),
            'retainedBinaryCount' => GameEvidence::query()->whereNotNull('path')->count(),
            'redactedCount' => GameEvidence::query()->whereNotNull('redacted_at')->count(),
        ];
    }
}
