<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Console\Commands;

use App\Contexts\Intelligence\Evidence\Queries\EvidenceDiagnosticsQuery;
use Illuminate\Console\Command;

final class EvidenceDiagnosticsCommand extends Command
{
    protected $signature = 'evidence:diagnostics';

    protected $description = 'Show privacy-safe Screenshot Intake processing and retention diagnostics.';

    public function handle(EvidenceDiagnosticsQuery $diagnostics): int
    {
        $summary = $diagnostics->summary();
        $this->components->info('Screenshot Intake diagnostics');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Classification attempts', (string) $summary['classificationAttempts']],
                ['Classification failures', (string) $summary['classificationFailures']],
                ['Classification failure rate', $this->percent((float) $summary['classificationFailureRate'])],
                ['Average classification latency', $this->seconds($summary['classificationAverageLatencySeconds'])],
                ['Extraction attempts', (string) $summary['extractionAttempts']],
                ['Extraction failures', (string) $summary['extractionFailures']],
                ['Extraction failure rate', $this->percent((float) $summary['extractionFailureRate'])],
                ['Average extraction latency', $this->seconds($summary['extractionAverageLatencySeconds'])],
                ['Reviewed rows', (string) $summary['reviewRows']],
                ['Corrected rows', (string) $summary['correctedRows']],
                ['Review correction rate', $this->percent((float) $summary['reviewCorrectionRate'])],
                ['Semantic duplicates', (string) $summary['semanticDuplicates']],
                ['Semantic duplicate rate', $this->percent((float) $summary['semanticDuplicateRate'])],
                ['Exact duplicate events', (string) $summary['exactDuplicateEvents']],
                ['Visual duplicate events', (string) $summary['visualDuplicateEvents']],
                ['Commit attempts', (string) $summary['commitAttempts']],
                ['Commit failures', (string) $summary['commitFailures']],
                ['Commit failure rate', $this->percent((float) $summary['commitFailureRate'])],
                ['Retention failures', (string) $summary['retentionFailures']],
                ['Retained binaries', (string) $summary['retainedBinaryCount']],
                ['Redacted evidence', (string) $summary['redactedCount']],
                ['Oldest processing timestamp', (string) ($summary['oldestProcessingAt'] ?? 'none')],
                ['Oldest processing age', $this->seconds($summary['oldestProcessingAgeSeconds'])],
            ],
        );

        $statusRows = [];
        $statusCounts = $summary['evidenceByStatus'] ?? [];
        if (is_array($statusCounts)) {
            foreach ($statusCounts as $status => $count) {
                $statusRows[] = [(string) $status, (string) (int) $count];
            }
        }
        $this->newLine();
        $this->table(['Lifecycle status', 'Count'], $statusRows);

        return self::SUCCESS;
    }

    private function percent(float $value): string
    {
        return number_format($value * 100, 2).'%';
    }

    private function seconds(mixed $value): string
    {
        return $value === null ? 'none' : number_format((float) $value, 3).' s';
    }
}
