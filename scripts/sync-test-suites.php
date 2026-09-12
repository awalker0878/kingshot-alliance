<?php

declare(strict_types=1);

require_once __DIR__.'/test-layout.php';

try {
    $arguments = array_slice($argv, 1);
    if ($arguments !== [] && $arguments !== ['--check']) {
        throw new InvalidArgumentException('Usage: php scripts/sync-test-suites.php [--check]');
    }
    $root = dirname(__DIR__);
    $path = $root.'/phpunit.xml';
    $configuration = file_get_contents($path);
    if ($configuration === false) {
        throw new RuntimeException('Cannot read PHPUnit configuration.');
    }
    $inventory = testLayoutInventory($root);
    $replacement = testLayoutSuiteXml($inventory);
    $updated = preg_replace_callback(
        '/^    <testsuites>.*?^    <\/testsuites>/ms',
        static fn (): string => $replacement,
        $configuration,
        count: $count,
    );
    if ($updated === null || $count !== 1) {
        throw new RuntimeException('Expected exactly one testsuites block.');
    }
    if ($arguments === ['--check'] && $updated !== $configuration) {
        throw new RuntimeException('Suite directories are stale. Run php scripts/sync-test-suites.php and commit phpunit.xml.');
    }
    if ($arguments === [] && $updated !== $configuration) {
        if (file_put_contents($path, $updated, LOCK_EX) !== strlen($updated)) {
            throw new RuntimeException('Could not write complete PHPUnit configuration.');
        }
    }
    fwrite(STDOUT, 'Suite paths reconciled for '.count($inventory)." PHP source files; no tests loaded or executed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
