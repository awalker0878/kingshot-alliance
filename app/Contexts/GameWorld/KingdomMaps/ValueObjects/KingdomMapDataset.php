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
        public string $observedAt,
        public string $sourceLabel,
        public ?string $sourceUri,
        public MapDatasetConfidence $confidence,
        public string $checksum,
        public array $data,
    ) {}
}
