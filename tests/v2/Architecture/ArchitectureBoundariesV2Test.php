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
        $directories = array_values(array_filter(
            scandir(base_path('app/Contexts')) ?: [],
            static fn (string $entry): bool => ! in_array($entry, ['.', '..', 'README.md'], true),
        ));
        sort($directories);

        self::assertSame(
            ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'],
            $directories,
        );
        self::assertDirectoryDoesNotExist(base_path('app/Domain'));
    }

    public function test_runtime_contains_no_v1_domain_namespace(): void
    {
        foreach ($this->phpFiles(['app', 'bootstrap', 'config', 'database', 'routes']) as $file) {
            self::assertStringNotContainsString('App\\Domain\\', (string) file_get_contents($file), $file);
        }
    }

    public function test_business_contexts_do_not_depend_on_composition_layers(): void
    {
        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('use App\\Workflows\\', $source, $file);

            $normalized = str_replace('\\', '/', $file);
            if (! str_contains($normalized, '/Http/')) {
                self::assertStringNotContainsString('use App\\ReadModels\\', $source, $file);
            }
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

    public function test_player_persistence_is_owned_by_game_world(): void
    {
        foreach ($this->phpFiles(['app/Contexts/Alliance', 'app/Contexts/Operations', 'app/Contexts/Intelligence', 'app/Contexts/Communications', 'app/Contexts/Platform', 'app/Workflows']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('Player::query()->create(', $source, $file);
        }
    }

    public function test_operations_and_intelligence_interpret_alliance_authority_locally(): void
    {
        foreach ($this->phpFiles(['app/Contexts/Operations', 'app/Contexts/Intelligence']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (str_contains($normalized, '/Access/Services/')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization', $source, $file);
            self::assertStringNotContainsString('App\\Contexts\\Alliance\\Access\\Services\\AllianceMutationAuthority', $source, $file);
        }
    }

    public function test_kingdom_transfer_owns_its_permission_vocabulary(): void
    {
        foreach ($this->phpFiles(['app/Workflows/KingdomTransfer']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('IntelligencePermission::', $source, $file);
            self::assertStringNotContainsString('App\\Contexts\\Intelligence\\Access\\', $source, $file);
        }

        self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Enums/TransferPermission.php'));
        self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Services/TransferAuthorization.php'));
        self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Services/TransferMutationAuthority.php'));
    }

    public function test_the_test_tree_contains_only_clean_room_v2_tests(): void
    {
        $entries = array_values(array_filter(
            scandir(base_path('tests')) ?: [],
            static fn (string $entry): bool => ! in_array($entry, ['.', '..'], true),
        ));
        self::assertSame(['v2'], $entries);

        foreach ($this->phpFiles(['tests/v2']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (str_contains($normalized, '/Support/') || str_ends_with($normalized, '/TestCase.php')) {
                continue;
            }
            self::assertStringEndsWith('V2Test.php', $normalized, $file);
            self::assertStringNotContainsString('App\\Domain\\', (string) file_get_contents($file), $file);
        }
    }

    /** @param list<string> $roots @return list<string> */
    private function phpFiles(array $roots): array
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
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }
}
