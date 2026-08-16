<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ArchitectureEnforcementV3Test extends TestCase
{
    #[Test]
    public function mutation_authority_pattern_does_not_exist(): void
    {
        $violations = [];
        foreach ($this->phpFiles(dirname(__DIR__, 3).'/app') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            if (preg_match('/\b[A-Za-z0-9_]*MutationAuthority\b/', $contents) === 1) {
                $violations[] = $this->relative($file);
            }
        }
        self::assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function context_models_do_not_declare_foreign_eloquent_relationships(): void
    {
        $contextsRoot = dirname(__DIR__, 3).'/app/Contexts';
        $violations = [];
        foreach (glob($contextsRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextPath) {
            $context = basename($contextPath);
            foreach ($this->phpFiles($contextPath) as $file) {
                if (! str_contains(str_replace('\\', '/', $file), '/Models/')) continue;
                $contents = file_get_contents($file);
                self::assertIsString($contents);
                if (preg_match_all('/^use App\\\\Contexts\\\\([A-Za-z0-9_]+)\\\\[^;]*\\\\Models\\\\([A-Za-z0-9_]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $contents, $matches, PREG_SET_ORDER) === false) continue;
                foreach ($matches as $match) {
                    if ($match[1] === $context) continue;
                    $alias = $match[3] !== '' ? $match[3] : $match[2];
                    if (preg_match('/(?:belongsTo|hasOne|hasMany|belongsToMany|morphOne|morphMany)\s*\(\s*'.preg_quote($alias, '/').'::class/', $contents) === 1) {
                        $violations[] = $this->relative($file).' -> '.$match[1].'\\'.$match[2];
                    }
                }
            }
        }
        sort($violations);
        self::assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function gameworld_player_does_not_reference_accounts_user_model(): void
    {
        $file = dirname(__DIR__, 3).'/app/Contexts/GameWorld/Players/Models/Player.php';
        $contents = file_get_contents($file);
        self::assertIsString($contents);
        self::assertStringNotContainsString('App\\Contexts\\Accounts\\Identity\\Models\\User', $contents);
        self::assertDoesNotMatchRegularExpression('/function\s+user\s*\(/', $contents);
    }

    #[Test]
    public function read_models_are_strictly_read_only(): void
    {
        $root = dirname(__DIR__, 3).'/app/ReadModels';
        $violations = [];
        if (! is_dir($root)) return;
        foreach ($this->phpFiles($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            foreach ([
                '/\bDB::transaction\s*\(/', '/->lockForUpdate\s*\(/', '/->forceFill\s*\(/',
                '/->save\s*\(\s*\)/', '/->delete\s*\(\s*\)/', '/::(?:query\(\)->)?create\s*\(/',
                '/->(?:insert|upsert|update)\s*\(/',
            ] as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $this->relative($file);
                    break;
                }
            }
        }
        self::assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function authorization_services_do_not_acquire_database_locks_or_transactions(): void
    {
        $violations = [];
        foreach ($this->phpFiles(dirname(__DIR__, 3).'/app/Contexts') as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (! str_contains($normalized, '/Access/') && ! str_contains($normalized, '/Authorization')) continue;
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            if (preg_match('/\bDB::transaction\s*\(|->lockForUpdate\s*\(|->sharedLock\s*\(/', $contents) === 1) {
                $violations[] = $this->relative($file);
            }
        }
        sort($violations);
        self::assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function write_actions_and_services_do_not_import_foreign_permission_vocabularies(): void
    {
        $contextsRoot = dirname(__DIR__, 3).'/app/Contexts';
        $violations = [];
        foreach (glob($contextsRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextPath) {
            $context = basename($contextPath);
            foreach ($this->phpFiles($contextPath) as $file) {
                $normalized = str_replace('\\', '/', $file);
                if (! str_contains($normalized, '/Actions/') && ! str_contains($normalized, '/Services/')) continue;
                $contents = file_get_contents($file);
                self::assertIsString($contents);
                if (preg_match_all('/^use App\\\\Contexts\\\\([A-Za-z0-9_]+)\\\\[^;]*(?:Access|Governance)\\\\(?:Enums|Permissions?)\\\\[^;]+;/m', $contents, $matches, PREG_SET_ORDER) === false) continue;
                foreach ($matches as $match) {
                    if ($match[1] !== $context) {
                        $violations[] = $this->relative($file).' imports '.$match[0];
                    }
                }
            }
        }
        sort($violations);
        self::assertSame([], $violations, implode("\n", $violations));
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        if (! is_dir($root)) return [];
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
        }
        return $files;
    }

    private function relative(string $file): string
    {
        return str_replace(dirname(__DIR__, 3).'/', '', str_replace('\\', '/', $file));
    }
}
