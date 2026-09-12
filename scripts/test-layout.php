<?php

declare(strict_types=1);

/** @return list<string> */
function testLayoutSuites(): array
{
    return ['Unit', 'Feature', 'Integration', 'Architecture', 'Frontend'];
}

/**
 * Read paths only. Never load a test class, bootstrap Laravel or evaluate a provider.
 *
 * @return array<string, array{suite: string, directory: string}>
 */
function testLayoutInventory(string $root): array
{
    $inventory = [];
    $suites = testLayoutSuites();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
            continue;
        }
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $parts = explode('/', $path);
        $tierPositions = array_keys(array_intersect($parts, $suites));
        if (count($tierPositions) !== 1) {
            throw new RuntimeException('A test must have exactly one execution tier: '.$path);
        }
        $position = $tierPositions[0];
        $area = $parts[1];
        $valid = match ($area) {
            'Contexts' => $position === 4,
            'ReadModels', 'Workflows' => $position === 3,
            'Shared' => $position >= 3,
            'System' => $position === 2 || ($parts[2] === 'Acceptance' && $position === 3),
            default => false,
        };
        if (! $valid || count($parts) <= $position + 1) {
            throw new RuntimeException('Expected owner/area before execution tier: '.$path);
        }
        $inventory[$path] = [
            'suite' => $parts[$position],
            'directory' => implode('/', array_slice($parts, 0, $position + 1)),
        ];
    }
    if ($inventory === []) {
        throw new RuntimeException('No PHP test source files found.');
    }
    ksort($inventory);

    return $inventory;
}

/** @param array<string, array{suite: string, directory: string}> $inventory */
function testLayoutSuiteXml(array $inventory): string
{
    $lines = ['    <testsuites>'];
    foreach (testLayoutSuites() as $suite) {
        $directories = [];
        foreach ($inventory as $entry) {
            if ($entry['suite'] === $suite) {
                $directories[$entry['directory']] = true;
            }
        }
        if ($directories === []) {
            throw new RuntimeException('Required suite is empty: '.$suite);
        }
        ksort($directories);
        $lines[] = '        <testsuite name="'.$suite.'">';
        foreach (array_keys($directories) as $directory) {
            $lines[] = '            <directory suffix="Test.php">'.htmlspecialchars($directory, ENT_XML1).'</directory>';
        }
        $lines[] = '        </testsuite>';
    }
    $lines[] = '    </testsuites>';

    return implode("\n", $lines);
}
