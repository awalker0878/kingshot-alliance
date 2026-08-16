<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v2\TestCase;

final class ArchitectureBoundariesV2Test extends TestCase
{
    public function test_only_the_seven_documented_business_contexts_exist(): void
    {
        $directories = array_values(array_filter(scandir(base_path('app/Contexts')) ?: [], static fn (string $entry): bool => ! in_array($entry, ['.', '..', 'README.md'], true)));
        sort($directories);

        self::assertSame(['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'], $directories);
        self::assertDirectoryDoesNotExist(base_path('app/Domain'));
    }

    public function test_business_contexts_do_not_depend_on_workflows(): void
    {
        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            self::assertStringNotContainsString('App\\Workflows\\', (string) file_get_contents($file), $file);
        }
    }

    public function test_player_has_no_eloquent_navigation_into_accounts(): void
    {
        $source = (string) file_get_contents(base_path('app/Contexts/GameWorld/Models/Player.php'));
        self::assertStringContainsString('user_id', $source);
        self::assertStringNotContainsString('App\\Contexts\\Accounts\\Models\\User', $source);
        self::assertStringNotContainsString('belongsTo(User::class', $source);
        self::assertDoesNotMatchRegularExpression('/function\s+user\s*\(/', $source);
    }

    public function test_authorization_is_not_hidden_behind_mutation_authority_classes(): void
    {
        foreach ($this->phpFiles(['app']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            self::assertStringNotContainsString('MutationAuthority.php', $normalized, $normalized);
            self::assertDoesNotMatchRegularExpression('/\bclass\s+\w*MutationAuthority\b/', (string) file_get_contents($file), $normalized);
        }
    }

    public function test_alliance_does_not_own_kingdom_governance_http_or_permissions(): void
    {
        foreach ($this->phpFiles(['app/Contexts/Alliance']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('class KingdomRoleController', $source, $file);
            self::assertStringNotContainsString('KingdomPermission::', $source, $file);
        }
    }

    public function test_communications_owns_generic_delivery_state_not_domain_delivery_models(): void
    {
        self::assertFileExists(base_path('app/Contexts/Communications/Delivery/Models/NotificationDelivery.php'));
        self::assertFileExists(base_path('app/Contexts/Communications/Delivery/Models/NotificationPreference.php'));
        self::assertFileExists(base_path('app/Contexts/Communications/Delivery/Services/NotificationDeliveryService.php'));

        foreach ($this->phpFiles(['app/Contexts/Communications']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            self::assertDoesNotMatchRegularExpression('#/(?:Event|KingPerk|AllianceChampion)\w*ReminderDelivery\.php$#', $normalized, $normalized);
        }
    }

    public function test_workflows_do_not_own_business_persistence_or_permission_vocabulary(): void
    {
        foreach ($this->phpFiles(['app/Workflows']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('#/(?:Models|Migrations|Repositories|Access)/(?:.*)$#', $normalized, $normalized);
            self::assertStringNotContainsString('extends Model', $source, $normalized);
            self::assertStringNotContainsString('Schema::', $source, $normalized);
            self::assertDoesNotMatchRegularExpression('/\benum\s+\w*Permission\b/', $source, $normalized);
        }
    }

    public function test_read_models_remain_read_only_composition(): void
    {
        foreach ($this->phpFiles(['app/ReadModels']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/::query\(\)->(?:create|update|delete)\s*\(/', $source, $file);
            self::assertDoesNotMatchRegularExpression('/->(?:save|delete)\s*\(/', $source, $file);
            self::assertStringNotContainsString('DB::transaction(', $source, $file);
        }
    }

    public function test_architecture_level_capability_registry_is_gone(): void
    {
        self::assertFileDoesNotExist(base_path('tests/v2/Architecture/CapabilityCoverageV2Test.php'));
        foreach ($this->files(['docs']) as $file) {
            if (! str_ends_with($file, '.md')) {
                continue;
            }
            $source = strtolower((string) file_get_contents($file));
            self::assertStringNotContainsString('capability coverage', $source, $file);
            self::assertStringNotContainsString('capability-coverage', $source, $file);
        }
    }

    /** @param list<string> $roots @return list<string> */
    private function phpFiles(array $roots): array
    {
        return array_values(array_filter($this->files($roots), static fn (string $file): bool => str_ends_with($file, '.php')));
    }

    /** @param list<string> $roots @return list<string> */
    private function files(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            $path = base_path($root);
            if (! is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            /** @var SplFileInfo $entry */
            foreach ($iterator as $entry) {
                if ($entry->isFile()) {
                    $files[] = $entry->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }
}
