<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

final readonly class ProgressionDataset
{
    /**
     * @param array<string,mixed> $release
     * @param list<array<string,mixed>> $heroes
     * @param array<string,mixed> $systems
     * @param list<array<string,mixed>> $formations
     */
    public function __construct(
        public string $id,
        public int $schemaVersion,
        public string $datasetVersion,
        public string $observedAt,
        public string $checksum,
        public array $release,
        public array $heroes,
        public array $systems,
        public array $formations,
    ) {}

    /** @return list<array<string,mixed>> */
    public function sources(): array
    {
        $sources = $this->release['sources'] ?? [];

        return is_array($sources) ? array_values(array_filter($sources, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function dispositions(): array
    {
        $items = $this->release['family_dispositions'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function conflicts(): array
    {
        $items = $this->release['conflicts'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
