<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use RuntimeException;

/** Validate factual spatial artifacts; presentation assets never enter this contract. */
final class KingdomMapSpatialArtifactValidator
{
    /**
     * @param  array<string,mixed>  $artifact
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $release
     */
    public function terrain(array $artifact, array $manifest, array $release): void
    {
        $bounds = $this->common($artifact, $manifest, $release);
        $this->require(($artifact['encoding'] ?? null) === 'horizontal_unit_height_spans', 'unsupported terrain encoding');
        $this->hash($artifact['bitmap_sha256'] ?? null);
        $features = $this->list($artifact['features'] ?? null, 10000, 'terrain features');
        $counts = ['lake' => 0, 'mountain' => 0];
        $cellCounts = ['lake' => 0, 'mountain' => 0];
        $occupied = str_repeat("\0", $bounds['width'] * $bounds['height']);
        $keys = [];
        $totalSpans = 0;
        foreach ($features as $feature) {
            $this->require(is_array($feature) && ! array_is_list($feature), 'terrain feature must be an object');
            $this->keys($feature, ['key', 'family', 'bounds', 'centroid', 'cell_count', 'spans']);
            $family = $feature['family'];
            $this->require(is_string($family) && array_key_exists($family, $counts), 'unsupported terrain family');
            $key = $this->key($feature['key']);
            $this->require(! isset($keys[$key]), 'duplicate terrain identity');
            $keys[$key] = true;
            $counts[$family]++;
            $rectangle = $this->rectangle($feature['bounds'], $bounds);
            $spans = $this->list($feature['spans'], 100000, 'terrain spans');
            $totalSpans += count($spans);
            $this->require($totalSpans <= 100000 && $spans !== [], 'terrain span limit');
            $previousY = null;
            $previousEnd = null;
            $cells = 0;
            $sumX = 0;
            $sumY = 0;
            $extent = ['left' => PHP_INT_MAX, 'bottom' => PHP_INT_MAX, 'right' => PHP_INT_MIN, 'top' => PHP_INT_MIN];
            foreach ($spans as $span) {
                $this->require(is_array($span) && array_is_list($span) && count($span) === 3, 'terrain span must be [x,y,width]');
                [$x, $y, $width] = $span;
                $this->require(is_int($x) && is_int($y) && is_int($width) && $width > 0, 'terrain spans require integer coordinates and width');
                $this->require(
                    $x >= $rectangle['x'] && $y >= $rectangle['y']
                    && $x + $width <= $rectangle['x'] + $rectangle['width']
                    && $y + 1 <= $rectangle['y'] + $rectangle['height'],
                    'terrain span exceeds its exact envelope',
                );
                $this->require(
                    $previousY === null || $y > $previousY || ($y === $previousY && $x > $previousEnd),
                    'terrain spans must be ordered, disjoint and horizontally merged',
                );
                $previousY = $y;
                $previousEnd = $x + $width;
                $offset = ($y - $bounds['y']) * $bounds['width'] + $x - $bounds['x'];
                for ($cell = $offset; $cell < $offset + $width; $cell++) {
                    $this->require($occupied[$cell] === "\0", 'terrain features overlap');
                    $occupied[$cell] = "\1";
                }
                $cells += $width;
                $sumX += intdiv($width * (2 * $x + $width - 1), 2);
                $sumY += $y * $width;
                $extent['left'] = min($extent['left'], $x);
                $extent['bottom'] = min($extent['bottom'], $y);
                $extent['right'] = max($extent['right'], $x + $width);
                $extent['top'] = max($extent['top'], $y + 1);
            }
            $this->require(is_int($feature['cell_count']) && $feature['cell_count'] === $cells, 'terrain cell count differs from geometry');
            $this->require(
                $extent === ['left' => $rectangle['x'], 'bottom' => $rectangle['y'], 'right' => $rectangle['x'] + $rectangle['width'], 'top' => $rectangle['y'] + $rectangle['height']],
                'terrain envelope differs from exact cell extent',
            );
            $centroid = $feature['centroid'];
            $this->require(is_array($centroid) && ! array_is_list($centroid), 'terrain centroid must be an object');
            $this->keys($centroid, ['x', 'y']);
            $this->require($this->finite($centroid['x']) && $this->finite($centroid['y']), 'terrain centroid must be finite');
            $this->require(abs($sumX / $cells - $centroid['x']) <= 0.051 && abs($sumY / $cells - $centroid['y']) <= 0.051, 'terrain centroid differs from exact cells');
            $cellCounts[$family] += $cells;
        }
        $this->require(($artifact['feature_counts'] ?? null) === $counts, 'terrain feature totals differ from records');
        $this->require(($artifact['cell_counts'] ?? null) === $cellCounts, 'terrain cell totals differ from records');
        $layer = $release['resource_layers']['terrain'];
        $this->require($counts['lake'] === $layer['lakes'] && $counts['mountain'] === $layer['mountains'], 'terrain source count differs from released count');
    }

    /**
     * @param  array<string,mixed>  $artifact
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $release
     */
    public function resources(array $artifact, array $manifest, array $release): void
    {
        $bounds = $this->common($artifact, $manifest, $release);
        $this->require(($artifact['ownership_semantics'] ?? null) === 'unqualified_resource_node', 'unqualified resource records cannot assert Alliance production');
        $footprintSource = $release['sources'][$artifact['provenance'][1] ?? ''] ?? null;
        $this->require(
            is_array($footprintSource) && ($artifact['footprint_source_sha256'] ?? null) === ($footprintSource['snapshot_sha256'] ?? null),
            'resource footprint source is not pinned',
        );
        $this->hash($artifact['footprint_source_sha256'] ?? null);
        $nodes = $this->list($artifact['nodes'] ?? null, 25000, 'resource nodes');
        $this->require(count($nodes) === $release['resource_layers']['resource_nodes']['count'], 'resource source count differs from released count');
        $keys = [];
        $positions = [];
        $previous = null;
        foreach ($nodes as $node) {
            $this->require(is_array($node) && ! array_is_list($node), 'resource node must be an object');
            $this->keys($node, ['key', 'resource_type', 'x', 'y', 'footprint']);
            $key = $this->key($node['key']);
            $this->require(! isset($keys[$key]), 'duplicate resource identity');
            $keys[$key] = true;
            $this->require(is_int($node['x']) && is_int($node['y']), 'resource coordinates must be integers');
            $this->require($key === 'r_'.$node['x'].'_'.$node['y'], 'resource key does not match the source coordinate identity');
            $this->require(in_array($node['resource_type'], ['bread', 'woodmill', 'quarry', 'ironmine'], true), 'unsupported resource type');
            $this->require($node['footprint'] === ['width' => 2, 'height' => 2], 'resource footprint differs from its pinned source definition');
            $this->rectangle(['x' => $node['x'], 'y' => $node['y'], 'width' => 2, 'height' => 2], $bounds);
            $position = $node['x'].','.$node['y'];
            $this->require(! isset($positions[$position]), 'duplicate resource position');
            $positions[$position] = true;
            $this->require($previous === null || $node['y'] > $previous['y'] || ($node['y'] === $previous['y'] && $node['x'] > $previous['x']), 'resource records must have canonical row-major order');
            $previous = $node;
        }
        $this->list($artifact['diagnostics'] ?? null, 25000, 'source diagnostics');
    }

    /**
     * Cross-layer inconsistencies are part of the evidence, not reasons to fabricate
     * replacement coordinates. Recompute diagnostics rather than trusting a label.
     *
     * @param  list<array<string,mixed>>  $features
     * @param  list<array<string,mixed>>  $nodes
     * @param  array{x:int,y:int,width:int,height:int}  $bounds
     * @return list<array{code:string,resource_keys:list<string>,terrain_keys:list<string>}>
     */
    public function diagnostics(array $features, array $nodes, array $bounds): array
    {
        $labels = str_repeat("\0", $bounds['width'] * $bounds['height'] * 2);
        foreach ($features as $index => $feature) {
            $label = pack('v', $index + 1);
            foreach ($feature['spans'] as [$x, $y, $width]) {
                $offset = (($y - $bounds['y']) * $bounds['width'] + $x - $bounds['x']) * 2;
                for ($cell = $offset; $cell < $offset + $width * 2; $cell += 2) {
                    $labels[$cell] = $label[0];
                    $labels[$cell + 1] = $label[1];
                }
            }
        }
        $occupied = [];
        $pairs = [];
        $diagnostics = [];
        foreach ($nodes as $index => $node) {
            $terrainKeys = [];
            for ($y = $node['y']; $y < $node['y'] + $node['footprint']['height']; $y++) {
                for ($x = $node['x']; $x < $node['x'] + $node['footprint']['width']; $x++) {
                    $cell = ($y - $bounds['y']) * $bounds['width'] + $x - $bounds['x'];
                    $label = ord($labels[$cell * 2]) + ord($labels[$cell * 2 + 1]) * 256;
                    if ($label > 0) {
                        $terrainKeys[$features[$label - 1]['key']] = true;
                    }
                    if (isset($occupied[$cell])) {
                        $keys = [$nodes[$occupied[$cell]]['key'], $node['key']];
                        sort($keys, SORT_STRING);
                        $pairs[implode('|', $keys)] = $keys;
                    }
                    $occupied[$cell] = $index;
                }
            }
            if ($terrainKeys !== []) {
                $keys = array_keys($terrainKeys);
                sort($keys, SORT_STRING);
                $diagnostics[] = ['code' => 'resource_terrain_overlap', 'resource_keys' => [$node['key']], 'terrain_keys' => $keys];
            }
        }
        ksort($pairs, SORT_STRING);
        foreach ($pairs as $keys) {
            $diagnostics[] = ['code' => 'resource_footprint_overlap', 'resource_keys' => $keys, 'terrain_keys' => []];
        }

        return $diagnostics;
    }

    /**
     * @param  array<string,mixed>  $artifact
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $release
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function common(array $artifact, array $manifest, array $release): array
    {
        $this->require(($artifact['schema_version'] ?? null) === 1, 'unsupported spatial artifact schema');
        $this->require(($artifact['game'] ?? null) === 'kingshot', 'spatial artifact must be Kingshot-specific');
        $this->require(($artifact['coordinate_system'] ?? null) === 'kingshot_xy_south_west', 'unsupported coordinate interpretation');
        $this->require(($artifact['completeness'] ?? null) === 'complete_captured_source', 'spatial source completeness is not established');
        $this->require(($artifact['confidence'] ?? null) === ($manifest['confidence'] ?? null), 'spatial confidence differs from manifest');
        $this->require(($artifact['provenance'] ?? null) === ($manifest['provenance'] ?? null), 'spatial provenance differs from manifest');
        $this->require(($artifact['observed_at'] ?? null) === ($manifest['observed_at'] ?? null), 'spatial observation time differs from manifest');
        $observed = $artifact['observed_at'] ?? null;
        $this->require(is_string($observed) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $observed) === 1, 'spatial observation time must be an explicit UTC instant');
        $timestamp = strtotime($observed);
        $this->require($timestamp !== false && gmdate('Y-m-d\TH:i:s\Z', $timestamp) === $observed, 'invalid spatial observation instant');
        $source = $release['sources'][$artifact['provenance'][0] ?? ''] ?? null;
        $this->require(is_array($source) && ($artifact['source_sha256'] ?? null) === ($source['snapshot_sha256'] ?? null), 'source snapshot is not pinned');
        $this->hash($artifact['source_sha256'] ?? null);
        $bounds = $this->rectangle($artifact['bounds'] ?? null);
        foreach ($bounds as $key => $value) {
            $this->require($value === $release['bounds'][$key], 'spatial bounds differ from release');
        }

        return $bounds;
    }

    /**
     * @param  array{x:int,y:int,width:int,height:int}|null  $bounds
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function rectangle(mixed $value, ?array $bounds = null): array
    {
        $this->require(is_array($value) && ! array_is_list($value), 'spatial rectangle must be an object');
        $this->keys($value, ['x', 'y', 'width', 'height']);
        foreach (['x', 'y', 'width', 'height'] as $key) {
            $this->require(is_int($value[$key]), 'spatial rectangles require exact integers');
        }
        $this->require(abs($value['x']) <= 1000000 && abs($value['y']) <= 1000000 && $value['width'] > 0 && $value['height'] > 0 && $value['width'] <= 1200 && $value['height'] <= 1200, 'spatial rectangle bounds exceed limits');
        if ($bounds !== null) {
            $this->require($value['x'] >= $bounds['x'] && $value['y'] >= $bounds['y'] && $value['x'] + $value['width'] <= $bounds['x'] + $bounds['width'] && $value['y'] + $value['height'] <= $bounds['y'] + $bounds['height'], 'spatial rectangle is outside release');
        }

        return $value;
    }

    /** @return list<mixed> */
    private function list(mixed $value, int $limit, string $label): array
    {
        $this->require(is_array($value) && array_is_list($value) && count($value) <= $limit, $label.' must be a bounded list');

        return $value;
    }

    /**
     * @param  array<string,mixed>  $value
     * @param  list<string>  $expected
     */
    private function keys(array $value, array $expected): void
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        $this->require($keys === $expected, 'unexpected or missing spatial contract field');
    }

    private function key(mixed $value): string
    {
        $this->require(is_string($value) && preg_match('/^[a-z][a-z0-9_]{1,79}$/', $value) === 1, 'invalid spatial record key');

        return $value;
    }

    private function hash(mixed $value): void
    {
        $this->require(is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1, 'invalid spatial source SHA-256');
    }

    private function finite(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException('Kingdom map spatial artifact: '.$message.'.');
        }
    }
}
