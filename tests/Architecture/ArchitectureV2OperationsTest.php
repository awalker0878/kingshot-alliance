<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureV2OperationsTest extends TestCase
{
    #[Test]
    public function legacy_operational_domains_are_deleted(): void
    {
        foreach (['Events', 'Rallies', 'KingPerks'] as $domain) {
            self::assertDirectoryDoesNotExist($this->root().'/app/Domain/'.$domain);
        }
    }

    #[Test]
    public function legacy_operational_namespaces_are_absent(): void
    {
        $forbidden = [
            'App\\Domain\\Events\\',
            'App\\Domain\\Rallies\\',
            'App\\Domain\\KingPerks\\',
        ];

        foreach ($this->phpFiles($this->root()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            foreach ($forbidden as $namespace) {
                self::assertStringNotContainsString(
                    $namespace,
                    $source,
                    $file.' still references a superseded P5 namespace.',
                );
            }
        }
    }

    #[Test]
    public function operations_does_not_depend_on_downstream_contexts(): void
    {
        $forbidden = [
            'App\\Contexts\\Intelligence\\',
            'App\\Contexts\\Communications\\',
            'App\\Contexts\\Platform\\',
            'App\\ReadModels\\',
        ];

        $files = $this->phpFiles($this->root().'/app/Contexts/Operations');
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            foreach ($forbidden as $namespace) {
                self::assertStringNotContainsString(
                    $namespace,
                    $source,
                    $file.' violates the Operations dependency direction.',
                );
            }
        }
    }

    #[Test]
    public function event_management_commands_and_cross_context_projection_have_separate_owners(): void
    {
        $commandPath = $this->root().'/app/Contexts/Operations/EventCore/Http/Controllers/EventManagementController.php';
        $projectionPath = $this->root().'/app/ReadModels/EventManagement/Http/Controllers/EventManagementPageController.php';
        $routesPath = $this->root().'/routes/web.php';

        self::assertFileExists($commandPath);
        self::assertFileExists($projectionPath);

        $commandSource = file_get_contents($commandPath);
        $projectionSource = file_get_contents($projectionPath);
        $routesSource = file_get_contents($routesPath);

        self::assertIsString($commandSource);
        self::assertIsString($projectionSource);
        self::assertIsString($routesSource);

        self::assertStringNotContainsString('EventPlayerIntelligenceQuery', $commandSource);
        self::assertStringNotContainsString('function manage(', $commandSource);
        self::assertStringContainsString('EventPlayerIntelligenceQuery', $projectionSource);
        self::assertStringContainsString('EventManagementPageController::class', $routesSource);
        self::assertDirectoryDoesNotExist($this->root().'/app/Contexts/Platform/EventOperations');
    }

    #[Test]
    public function event_reminder_policy_does_not_navigate_into_delivery_state(): void
    {
        $path = $this->root().'/app/Contexts/Operations/Reminders/Models/EventReminderRule.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringNotContainsString('EventReminderDelivery', $source);
        self::assertStringNotContainsString('function deliveries(', $source);
    }

    #[Test]
    public function event_type_administration_is_platform_owned(): void
    {
        self::assertFileExists($this->root().'/app/Contexts/Platform/EventAdministration/Actions/UpdateEventTypeScope.php');
        self::assertFileExists($this->root().'/app/Contexts/Platform/EventAdministration/Http/Controllers/EventTypeAdministrationController.php');
        self::assertFileDoesNotExist($this->root().'/app/Contexts/Operations/EventCore/Actions/UpdateEventTypeScope.php');
        self::assertFileDoesNotExist($this->root().'/app/Contexts/Operations/EventCore/Http/Controllers/EventTypeAdministrationController.php');
    }

    /** @return list<string> */
    private function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $normalized = str_replace('\\', '/', $file->getPathname());
            if (str_contains($normalized, '/vendor/')) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
