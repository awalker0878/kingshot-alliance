<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class TransactionalMutationArchitectureTest extends TestCase
{
    public function test_repository_documents_transactional_mutation_principles(): void
    {
        $adr = $this->root().'/docs/adr/0010-transactional-mutation-authority.md';
        self::assertFileExists($adr);

        $source = file_get_contents($adr);
        self::assertIsString($source);
        self::assertStringContainsString('domain-owned', strtolower($source));
        self::assertStringContainsString('compare-and-set', strtolower($source));
        self::assertStringContainsString('lock ordering is deterministic', strtolower($source));
        self::assertStringContainsString('external side effects', strtolower($source));
    }

    public function test_scope_specific_mutation_authority_boundaries_exist(): void
    {
        foreach ([
            'app/Contexts/Alliance/Access/Services/AllianceMutationAuthority.php',
            'app/Contexts/GameWorld/Governance/Services/KingdomMutationAuthority.php',
            'app/Contexts/GameWorld/Governance/Services/PlayerMutationAuthority.php',
            'app/Contexts/Platform/Access/Services/PlatformMutationAuthority.php',
            'app/Contexts/Operations/EventCore/Services/EventMutationAuthority.php',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, $path);
        }
    }

    public function test_read_authorization_services_do_not_acquire_write_locks(): void
    {
        foreach ([
            'app/Contexts/Alliance/Access/Services/AllianceAuthorization.php',
            'app/Contexts/GameWorld/Governance/Services/KingdomAuthorization.php',
        ] as $path) {
            $source = file_get_contents($this->root().'/'.$path);
            self::assertIsString($source);
            self::assertStringNotContainsString('lockForUpdate(', $source, $path.' must remain read-only authorization.');
            self::assertStringNotContainsString('sharedLock(', $source, $path.' must remain read-only authorization.');
        }
    }

    public function test_deprecated_lock_aware_read_authorization_apis_cannot_return(): void
    {
        foreach ($this->v2PhpFiles() as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('allowsForUpdate(', $source, $file);
            self::assertStringNotContainsString('activeMembershipForUpdate(', $source, $file);
        }
    }

    public function test_repository_does_not_use_a_generic_persistent_mutation_lock_table(): void
    {
        self::assertFileDoesNotExist(
            $this->root().'/database/migrations/2026_08_14_000001_create_mutation_locks_table.php',
        );

        foreach ($this->v2PhpFiles() as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString("'mutation_locks'", $source, $file);
            self::assertStringNotContainsString('"mutation_locks"', $source, $file);
        }
    }

    /** @return list<string> */
    private function v2PhpFiles(): array
    {
        $files = [];

        foreach ([
            $this->root().'/app/Contexts',
            $this->root().'/app/Workflows',
            $this->root().'/app/ReadModels',
            $this->root().'/app/Shared',
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = [...$files, ...$this->phpFiles($directory)];
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
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

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
