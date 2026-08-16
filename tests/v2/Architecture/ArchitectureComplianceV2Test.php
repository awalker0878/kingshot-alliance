<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v2\TestCase;

final class ArchitectureComplianceV2Test extends TestCase
{
    public function test_p1_canonical_source_shape_has_no_compatibility_scaffolding(): void
    {
        self::assertSame(['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'], $this->directories('app/Contexts', ['README.md']));
        foreach ($this->files(['app', 'bootstrap', 'config', 'database', 'routes']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            self::assertDoesNotMatchRegularExpression('#/(?:Legacy|Compatibility|Compat|Shim)(?:/|[A-Z_.-])#', $normalized, $normalized);
            if (str_ends_with($normalized, '.php')) {
                $source = (string) file_get_contents($file);
                self::assertStringNotContainsString('class_alias(', $source, $normalized);
                self::assertStringNotContainsString('App\\Domain\\', $source, $normalized);
            }
        }
    }

    public function test_p2_user_and_player_are_separate_aggregates_connected_by_scalar_identity(): void
    {
        $player = (string) file_get_contents(base_path('app/Contexts/GameWorld/Models/Player.php'));
        $context = (string) file_get_contents(base_path('app/Contexts/GameWorld/Services/PlayerContext.php'));
        self::assertStringContainsString('user_id', $player);
        self::assertStringNotContainsString('App\\Contexts\\Accounts\\Models\\User', $player);
        self::assertStringNotContainsString('belongsTo(User::class', $player);
        self::assertDoesNotMatchRegularExpression('/function\s+user\s*\(/', $player);
        self::assertStringContainsString('Player $player, User $user', $context);
        self::assertStringContainsString('The active Player must belong to the authenticated User.', $context);
    }

    public function test_p3_alliance_owns_alliance_authority_but_not_kingdom_governance(): void
    {
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Core'));
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Membership'));
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Recruitment'));
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Content'));
        self::assertFileDoesNotExist(base_path('app/Contexts/Alliance/Access/Http/Controllers/KingdomRoleController.php'));
        self::assertFileExists(base_path('app/Contexts/GameWorld/Governance/Http/Controllers/KingdomRoleController.php'));
    }

    public function test_p4_contexts_own_permission_vocabulary_without_mutation_authority_abstractions(): void
    {
        foreach ($this->phpFiles(['app']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            self::assertStringNotContainsString('MutationAuthority.php', $normalized, $normalized);
            self::assertDoesNotMatchRegularExpression('/\b\w*MutationAuthority\b/', (string) file_get_contents($file), $normalized);
        }
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Operations/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Intelligence/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Platform/Access'));
    }

    public function test_p5_operations_contains_complete_live_coordination_ownership(): void
    {
        self::assertSame(['Access', 'BattlePlans', 'EventCore', 'KingPerks', 'Participation', 'Polls', 'Rallies', 'Results', 'Rosters'], $this->directories('app/Contexts/Operations', ['README.md']));
    }

    public function test_p6_intelligence_contains_observation_analysis_and_sharing_ownership(): void
    {
        self::assertSame(['Access', 'Contributions', 'Diplomacy', 'EventAnalysis', 'Http', 'Ingestion', 'Observations', 'Roster', 'Sharing'], $this->directories('app/Contexts/Intelligence', ['README.md']));
    }

    public function test_p7_communications_is_general_delivery_infrastructure(): void
    {
        foreach (['NotificationDelivery.php', 'NotificationPreference.php'] as $model) {
            self::assertFileExists(base_path('app/Contexts/Communications/Delivery/Models/'.$model));
        }
        self::assertFileExists(base_path('app/Contexts/Communications/Delivery/Services/NotificationDeliveryService.php'));
        self::assertFileExists(base_path('app/Contexts/Operations/Participation/Reminders/Actions/QueueDueEventReminders.php'));
        self::assertFileExists(base_path('app/Contexts/Operations/KingPerks/Actions/QueueDueKingPerkReminders.php'));

        $migration = (string) file_get_contents(base_path('database/migrations/2026_08_16_000000_create_notification_delivery_tables.php'));
        foreach (['notification_deliveries', 'notification_preferences', 'idempotency_key', 'attempt_count', 'channel', 'next_attempt_at'] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }

        foreach ($this->phpFiles(['app/Contexts/Communications']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('App\\Contexts\\Operations\\', $source, $file);
            self::assertStringNotContainsString('App\\Contexts\\Alliance\\', $source, $file);
            self::assertStringNotContainsString('App\\Contexts\\GameWorld\\', $source, $file);
        }

        foreach ($this->phpFiles(['database/migrations']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString("Schema::create('event_reminder_deliveries'", $source, $file);
            self::assertStringNotContainsString("Schema::create('king_perk_reminder_deliveries'", $source, $file);
        }
    }

    public function test_p8_workflows_compose_but_own_no_persistence(): void
    {
        foreach ($this->phpFiles(['app/Workflows']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('#/(?:Models|Migrations|Repositories|Access)/#', $normalized, $normalized);
            self::assertStringNotContainsString('extends Model', $source, $normalized);
            self::assertStringNotContainsString('Schema::', $source, $normalized);
        }
        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            self::assertStringNotContainsString('App\\Workflows\\', (string) file_get_contents($file), $file);
        }
    }

    public function test_p9_governance_checks_actual_boundaries_without_capability_registry(): void
    {
        self::assertSame(['v2'], $this->directories('tests'));
        self::assertFileDoesNotExist(base_path('tests/v2/Architecture/CapabilityCoverageV2Test.php'));
        self::assertFileExists(base_path('docs/governance/architecture-compliance.md'));
        foreach ($this->files(['docs']) as $file) {
            if (! str_ends_with($file, '.md')) {
                continue;
            }
            $source = strtolower((string) file_get_contents($file));
            self::assertStringNotContainsString('capability coverage', $source, $file);
            self::assertStringNotContainsString('capability-coverage', $source, $file);
        }
    }

    /** @param list<string> $ignored @return list<string> */
    private function directories(string $root, array $ignored = []): array
    {
        $entries = array_values(array_filter(scandir(base_path($root)) ?: [], static fn (string $entry): bool => ! in_array($entry, ['.', '..', ...$ignored], true)));
        sort($entries);
        return $entries;
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

    /** @param list<string> $roots @return list<string> */
    private function phpFiles(array $roots): array
    {
        return array_values(array_filter($this->files($roots), static fn (string $file): bool => str_ends_with($file, '.php')));
    }
}
