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
        self::assertStringContainsString('public function store(', $commandSource);
        self::assertStringContainsString('public function update(', $commandSource);
        self::assertStringContainsString('public function cancel(', $commandSource);
        self::assertStringContainsString('EventPlayerIntelligenceQuery', $projectionSource);
        self::assertStringContainsString(
            "Route::get('/events/{event}/manage', EventManagementPageController::class)",
            $routesSource,
        );
        self::assertStringContainsString(
            "Route::post('/events', [EventManagementController::class, 'store'])",
            $routesSource,
        );
        self::assertStringContainsString(
            "Route::patch('/events/{event}', [EventManagementController::class, 'update'])",
            $routesSource,
        );
        self::assertDirectoryDoesNotExist($this->root().'/app/Contexts/Platform/EventOperations');
    }

    #[Test]
    public function kingdom_event_permissions_are_interpreted_by_operations_not_game_world(): void
    {
        $authorization = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventAuthorization.php');
        $creation = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventCreationMutationAuthority.php');
        $mutation = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventMutationAuthority.php');
        $operationsMutation = file_get_contents($this->root().'/app/Contexts/Operations/Access/Services/KingdomOperationsMutationAuthority.php');
        $gameWorldMutation = file_get_contents($this->root().'/app/Contexts/GameWorld/Governance/Services/KingdomMutationAuthority.php');

        foreach ([$authorization, $creation, $mutation, $operationsMutation, $gameWorldMutation] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('KingdomOperationsAuthorization', $authorization);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomAuthorization', $authorization);
        self::assertStringContainsString('KingdomOperationsMutationAuthority', $creation);
        self::assertStringContainsString('KingdomOperationsMutationAuthority', $mutation);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomMutationAuthority', $creation);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomMutationAuthority', $mutation);
        self::assertStringContainsString('acquireActiveScope', $operationsMutation);
        self::assertStringContainsString('public function acquireActiveScope', $gameWorldMutation);
    }

    #[Test]
    public function p5_acceptance_tests_are_owned_by_operations(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/tests/Feature/Events');
        self::assertDirectoryDoesNotExist($this->root().'/tests/Feature/KingPerks');
        self::assertDirectoryExists($this->root().'/tests/Feature/Operations/EventCore');
        self::assertDirectoryExists($this->root().'/tests/Feature/Operations/KingPerks');
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
