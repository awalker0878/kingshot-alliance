<?php

declare(strict_types=1);

// Standalone, dependency-free guard; safe to require from source certification.
(static function (): void {
    $root = dirname(__DIR__);
    $errors = [];
    $suites = ['Unit', 'Feature', 'Integration', 'Architecture', 'Frontend'];
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
        $actual[$name] = true;
        if (count($suite->children()) !== 1 || count($suite->directory) !== 1
            || (string) $suite->directory !== 'tests/'.$name
            || (string) $suite->directory['suffix'] !== 'Test.php') {
            $errors[] = 'Suite '.$name.' must exclusively discover tests/'.$name.'/*Test.php recursively.';
        }
    }
    if (array_diff($suites, array_keys($actual)) !== []) {
        $errors[] = 'One or more required test suites are missing.';
    }
    foreach (['v2', 'v3'] as $legacy) {
        if (is_dir($root.'/tests/'.$legacy)) {
            $errors[] = 'Versioned test root remains: '.$legacy;
        }
    }
    $classes = [];
    $testCount = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            $errors[] = 'Unreadable source: '.$relative;

            continue;
        }
        $kind = explode('/', $relative)[1];
        if (str_ends_with($relative, 'Test.php')) {
            $testCount++;
            if (! in_array($kind, $suites, true)) {
                $errors[] = 'Undiscovered test: '.$relative;
            }
            if (! preg_match('/^namespace ([^;]+);/m', $source)) {
                $errors[] = 'Test has no namespace: '.$relative;
            }
            if ($kind === 'Unit' && ! str_contains($source, 'use PHPUnit\\Framework\\TestCase;')) {
                $errors[] = 'Unit test must use the pure PHPUnit base: '.$relative;
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
        // Concatenation keeps the guard itself free from the banned executable namespace.
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
    if ($testCount === 0) {
        $errors[] = 'No PHP test classes were discovered.';
    }
    if ($errors !== []) {
        throw new RuntimeException("Test layout verification failed:\n - ".implode("\n - ", $errors));
    }
    fwrite(STDOUT, 'Test layout verified: '.$testCount." test files in five disjoint suites.\n");
})();
