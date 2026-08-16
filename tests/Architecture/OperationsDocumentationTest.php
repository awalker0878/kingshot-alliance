<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class OperationsDocumentationTest extends TestCase
{
    public function test_system_operations_is_distinct_from_the_operations_business_context(): void
    {
        $index = $this->read('docs/operations/README.md');

        self::assertStringContainsString('business `Operations` bounded context', $index);
        self::assertStringContainsString('deployment', strtolower($index));
        self::assertStringContainsString('recover', strtolower($index));
    }

    public function test_current_operational_surfaces_are_documented(): void
    {
        foreach ([
            'docs/operations/architecture.md',
            'docs/operations/configuration.md',
            'docs/operations/observability.md',
            'docs/operations/background-processing.md',
            'docs/operations/deployment/README.md',
            'docs/operations/deployment/azure.md',
            'docs/operations/runbooks/deployment.md',
            'docs/operations/runbooks/rollback.md',
            'docs/operations/runbooks/backup-restore.md',
            'docs/operations/runbooks/incident-response.md',
            'docs/operations/recovery/disaster-recovery.md',
            'docs/operations/release/checklist.md',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, $path);
        }
    }

    public function test_historical_phase_runbooks_are_not_retained_as_live_operations(): void
    {
        foreach (glob($this->root().'/docs/operations/phase-*.md') ?: [] as $file) {
            self::fail('Historical phase operations file remains in live docs: '.$file);
        }
    }

    public function test_backup_restore_documentation_uses_repository_helpers_and_full_recovery_set(): void
    {
        $runbook = $this->read('docs/operations/runbooks/backup-restore.md');

        self::assertStringContainsString('bin/backup', $runbook);
        self::assertStringContainsString('bin/restore', $runbook);
        self::assertStringContainsString('PostgreSQL data', $runbook);
        self::assertStringContainsString('private/durable media/object storage', $runbook);
        self::assertStringContainsString('application encryption key', $runbook);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
