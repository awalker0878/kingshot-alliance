<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\ValueObjects;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;

final readonly class KingdomMapDataset
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public string $id,
        public int $schemaVersion,
        public string $releaseStatus,
        public string $releasedAt,
        public string $observedAt,
        public ?string $gameVersion,
        public ?string $season,
        public ?string $predecessorId,
        public string $sourceLabel,
        public ?string $sourceUri,
        public MapDatasetConfidence $confidence,
        public string $checksum,
        public array $data,
    ) {}

    /** @return array<string,mixed> */
    public function objectDefinition(string $type): array
    {
        $definition = $this->data['object_types'][$type] ?? null;

        return is_array($definition) ? $definition : [];
    }

    /** @return array{width:int,height:int}|null */
    public function footprint(string $type): ?array
    {
        $footprint = $this->objectDefinition($type)['footprint'] ?? null;
        if (! is_array($footprint)) {
            return null;
        }

        return ['width' => (int) $footprint['width'], 'height' => (int) $footprint['height']];
    }

    /** @return array{width:int,height:int}|null */
    public function coverage(string $type): ?array
    {
        $coverage = $this->objectDefinition($type)['coverage'] ?? null;
        if (! is_array($coverage)) {
            return null;
        }

        return ['width' => (int) $coverage['width'], 'height' => (int) $coverage['height']];
    }

    /** @return array<string,mixed> */
    public function sources(): array
    {
        $sources = $this->data['sources'] ?? null;

        return is_array($sources) ? $sources : [];
    }

    /** @return array<string,mixed> */
    public function resourceLayers(): array
    {
        $layers = $this->data['resource_layers'] ?? null;

        return is_array($layers) ? $layers : [];
    }
}
