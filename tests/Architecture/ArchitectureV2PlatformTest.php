<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2PlatformTest extends TestCase
{
    public function test_p7_v1_runtime_roots_are_deleted(): void
    {
        foreach (['Notifications', 'Integrations', 'Platform'] as $legacy) {
            self::assertDirectoryDoesNotExist($this->root().'/app/Domain/'.$legacy);
        }

        self::assertDirectoryExists($this->root().'/app/Contexts/Communications/Reminders');
        self::assertDirectoryExists($this->root().'/app/Contexts/Platform/Integrations');
        self::assertDirectoryExists($this->root().'/app/Shared/Infrastructure/AuditTrail');
        self::assertDirectoryExists($this->root().'/app/Shared/Infrastructure/Messaging/Outbox');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared/Audit');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared/Messaging');
        self::assertDirectoryExists($this->root().'/tests/Feature/Communications');
        self::assertDirectoryDoesNotExist($this->root().'/tests/RewriteInput/Communications');
    }

    public function test_contribution_report_scheduling_is_intelligence_owned(): void
    {
        self::assertFileExists(
            $this->root().'/app/Contexts/Intelligence/Contributions/Actions/QueueDueContributionReports.php',
        );
        self::assertFileDoesNotExist(
            $this->root().'/app/Contexts/Communications/Reminders/Actions/QueueDueContributionReports.php',
        );
    }

    public function test_production_runtime_has_no_p7_legacy_namespace_references(): void
    {
        $forbidden = [
            'App\\Domain\\Notifications\\',
            'App\\Domain\\Integrations\\',
            'App\\Domain\\Platform\\',
            'App\\Shared\\Audit\\',
            'App\\Shared\\Messaging\\',
        ];

        foreach (['app', 'routes', 'bootstrap', 'config', 'database'] as $root) {
            foreach ($this->phpFiles($this->root().'/'.$root) as $file) {
                $source = (string) file_get_contents($file);
                foreach ($forbidden as $namespace) {
                    self::assertStringNotContainsString(
                        $namespace,
                        $source,
                        $file.' still references legacy P7 ownership '.$namespace,
                    );
                }
            }
        }
    }

    public function test_shared_infrastructure_does_not_import_business_owners(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Shared/Infrastructure') as $file) {
            $source = (string) file_get_contents($file);
            foreach (['App\\Contexts\\', 'App\\Domain\\', 'App\\Workflows\\', 'App\\ReadModels\\'] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source,
                    $file.' makes Shared infrastructure depend on a business owner.',
                );
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
