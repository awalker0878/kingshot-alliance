<?php

declare(strict_types=1);

require_once __DIR__.'/test-layout.php';

// Standalone source guard: no test classes, providers or application are loaded.
(static function (): void {
    $root = dirname(__DIR__);
    $errors = [];
    // Transitional support is removed when the owner-first migration is complete.
    $inventory = testLayoutInventory($root, allowLegacy: true);
    $suites = testLayoutSuites();
    $configuration = simplexml_load_file($root.'/phpunit.xml');
    if ($configuration === false) {
        throw new RuntimeException('Cannot read PHPUnit configuration.');
    }
    $actual = [];
    foreach ($configuration->testsuites->testsuite as $suite) {
        $name = (string) $suite['name'];
        if (isset($actual[$name]) || ! in_array($name, $suites, true)) {
            $errors[] = 'Duplicate or unknown suite: '.$name;
        }
        $directories = [];
        foreach ($suite->children() as $directory) {
            if ($directory->getName() !== 'directory' || count($directory->attributes()) !== 1
                || (string) $directory['suffix'] !== 'Test.php') {
                $errors[] = 'Suite '.$name.' may only contain unfiltered Test.php directories.';
            }
            $directories[] = (string) $directory;
        }
        $expected = [];
        foreach ($inventory as $entry) {
            if ($entry['suite'] === $name) {
                $expected[$entry['directory']] = true;
            }
        }
        $expected = array_keys($expected);
        sort($expected);
        sort($directories);
        if ($directories !== $expected || $expected === []) {
            $errors[] = 'Missing, duplicated or stale suite paths for '.$name.'; run php scripts/sync-test-suites.php.';
        }
        $actual[$name] = true;
    }
    if (array_diff($suites, array_keys($actual)) !== []) {
        $errors[] = 'One or more required test suites are missing.';
    }
    foreach (['v2', 'v3'] as $legacy) {
        if (is_dir($root.'/tests/'.$legacy)) {
            $errors[] = 'Legacy test root remains: '.$legacy;
        }
    }
    $classes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            $errors[] = 'Unreadable source: '.$relative;

            continue;
        }
        if (isset($inventory[$relative])) {
            if (! preg_match('/^namespace ([^;]+);/m', $source)) {
                $errors[] = 'Test has no namespace: '.$relative;
            }
            if ($inventory[$relative]['suite'] === 'Unit' && ! str_contains($source, 'use PHPUnit\\Framework\\TestCase;')) {
                $errors[] = 'Unit test must use the pure PHPUnit base: '.$relative;
            }
            if (str_contains($source, 'Illuminate\\Foundation\\Testing\\DatabaseMigrations')
                && ! str_contains($relative, '/Integration/Schema/')) {
                $errors[] = 'Reserve DatabaseMigrations for an owner-local Integration/Schema contract: '.$relative;
            }
        }
        if (preg_match('/^namespace ([^;]+);/m', $source, $namespace)) {
            $expected = 'Tests'.str_replace('/', '\\', substr(dirname($relative), 5));
            if ($namespace[1] !== $expected) {
                $errors[] = 'Namespace/path mismatch: '.$relative;
            }
            if (preg_match('/^(?:(?:final|abstract) )?(?:class|trait|interface|enum) (\w+)/m', $source, $class)) {
                $identity = $namespace[1].'\\'.$class[1];
                if (isset($classes[$identity])) {
                    $errors[] = 'Duplicate declaration: '.$identity;
                }
                $classes[$identity] = $relative;
                if (basename($relative) !== $class[1].'.php') {
                    $errors[] = 'Declaration/filename mismatch: '.$relative;
                }
            }
        }
        if (str_contains($source, 'Tests\\'.'v3\\') || str_contains($source, 'Tests\\\\'.'v3\\\\')) {
            $errors[] = 'Stale test namespace: '.$relative;
        }
    }
    foreach (['.github/workflows', 'scripts'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['yml', 'yaml', 'php', 'mjs', 'ts', 'json'], true)) {
                continue;
            }
            $source = file_get_contents($file->getPathname()) ?: '';
            if (str_contains($source, 'tests/'.'v3/')) {
                $errors[] = 'Stale executable test path: '.substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }
    if ($errors !== []) {
        throw new RuntimeException("Test layout verification failed:\n - ".implode("\n - ", $errors));
    }
    fwrite(STDOUT, 'Test layout verified: '.count($inventory)." PHP source files assigned once across five suites; no tests executed.\n");
})();
