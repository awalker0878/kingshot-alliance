<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactResolution;

final readonly class ProgressionFactResult
{
    /**
     * @param  array<string, bool|float|int|string|null>  $values
     * @param  list<string>  $sourceIds
     */
    public function __construct(
        public ProgressionFactResolution $resolution,
        public string $family,
        public string $path,
        public string $title,
        public array $values,
        public string $datasetId,
        public string $datasetVersion,
        public string $checksum,
        public string $observedAt,
        public array $sourceIds = [],
        public ?string $confidence = null,
        public ?string $evidenceStatus = null,
    ) {}
}
