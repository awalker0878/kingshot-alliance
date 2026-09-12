<?php

declare(strict_types=1);

use Tests\Shared\Testing\Support\JUnitTimingReport;

require_once __DIR__.'/test-layout.php';
require_once dirname(__DIR__).'/tests/Shared/Testing/Support/JUnitTimingReport.php';

try {
    if ($argc < 2 || $argc > 3 || ($argc === 3 && ! preg_match('/^--limit=([1-9][0-9]?|100)$/', $argv[2], $match))) {
        throw new InvalidArgumentException('Usage: php scripts/summarize-test-timings.php <existing-junit.xml> [--limit=1..100]');
    }
    if (str_contains($argv[1], '://') || ! is_file($argv[1]) || ! is_readable($argv[1])) {
        throw new RuntimeException('The JUnit input must be an existing readable local file.');
    }
    $xml = file_get_contents($argv[1]);
    if ($xml === false) {
        throw new RuntimeException('Could not read the complete JUnit input.');
    }
    $report = JUnitTimingReport::parse($xml, testLayoutSuites());
    $markdown = JUnitTimingReport::markdown($report, $argc === 3 ? (int) $match[1] : 15);
    $markdown .= "\nInput SHA-256: `".hash('sha256', $xml)."`\n";
    if (fwrite(STDOUT, $markdown) !== strlen($markdown)) {
        throw new RuntimeException('Could not write the complete timing summary.');
    }
    exit(JUnitTimingReport::exitCode($report));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(2);
}
