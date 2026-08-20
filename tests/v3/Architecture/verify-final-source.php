<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$violations = [];
$record = static function (string $code, string $detail) use (&$violations): void {
    $violations[] = $code.': '.$detail;
};

$roots = [
    'App\\' => $root.'/app/',
    'Tests\\' => $root.'/tests/',
    'Database\\Factories\\' => $root.'/database/factories/',
    'Database\\Seeders\\' => $root.'/database/seeders/',
];

$scanRoots = [
    $root.'/app',
    $root.'/routes',
    $root.'/config',
    $root.'/database',
    $root.'/tests/v3',
];

foreach ($scanRoots as $scanRoot) {
    if (! is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname()) ?: '';
        if (preg_match_all('/^use\s+([^;]+);/m', $source, $matches) === false) {
            continue;
        }

        foreach ($matches[1] ?? [] as $import) {
            $import = trim((string) $import);
            if (str_starts_with($import, 'function ') || str_starts_with($import, 'const ') || str_contains($import, '{')) {
                continue;
            }
            $import = preg_replace('/\s+as\s+\w+$/', '', $import) ?? $import;

            foreach ($roots as $prefix => $base) {
                if (! str_starts_with($import, $prefix)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($import, strlen($prefix))).'.php';
                if (! is_file($base.$relative)) {
                    $record(
                        'MISSING_PROJECT_IMPORT',
                        str_replace($root.'/', '', $file->getPathname()).' -> '.$import,
                    );
                }
                break;
            }
        }
    }
}

foreach ([
    'README.md',
    'CONTRIBUTING.md',
    'app/Contexts/README.md',
    'docs/governance/architecture-compliance.md',
] as $relative) {
    $source = file_get_contents($root.'/'.$relative) ?: '';
    if (str_contains($source, 'Architecture V2')) {
        $record('CURRENT_DOCS_STILL_V2', $relative);
    }
    if ($relative !== 'docs/governance/architecture-compliance.md' && str_contains($source, 'tests/v2/Architecture')) {
        $record('CURRENT_DOCS_STILL_V2_TESTS', $relative);
    }
}

$phpunit = file_get_contents($root.'/phpunit.xml') ?: '';
if (! str_contains($phpunit, '<directory>tests/v3</directory>') || str_contains($phpunit, '<directory>tests/v2</directory>')) {
    $record('PHPUNIT_SUITE_NOT_V3_ONLY', 'phpunit.xml must execute tests/v3 only.');
}

$visualSpec = $root.'/tests/v3/Visual/ApplicationShell.spec.ts';
if (! is_file($visualSpec)) {
    $record('CURRENT_VISUAL_SUITE_MISSING', 'The current application-shell visual contract must remain under tests/v3/Visual.');
}

if ($violations !== []) {
    fwrite(STDERR, "V3 final source certification failed:\n - ".implode("\n - ", $violations)."\n");
    exit(1);
}

fwrite(STDOUT, "V3 final source certification passed (visual and CI workflow rewrites excluded).\n");
