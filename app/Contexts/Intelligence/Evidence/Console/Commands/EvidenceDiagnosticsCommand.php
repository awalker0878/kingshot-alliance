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
                ['Classification failures', (string) $summary['classificationFailures']],
                ['Extraction failures', (string) $summary['extractionFailures']],
                ['Commit failures', (string) $summary['commitFailures']],
                ['Retained binaries', (string) $summary['retainedBinaryCount']],
                ['Redacted evidence', (string) $summary['redactedCount']],
                ['Oldest processing timestamp', (string) ($summary['oldestProcessingAt'] ?? 'none')],
            ],
        );
        $this->newLine();
        $this->table(
            ['Lifecycle status', 'Count'],
            array_map(
                static fn (string $status, int $count): array => [$status, (string) $count],
                array_keys($summary['evidenceByStatus']),
                array_values($summary['evidenceByStatus']),
            ),
        );

        return self::SUCCESS;
    }
}
