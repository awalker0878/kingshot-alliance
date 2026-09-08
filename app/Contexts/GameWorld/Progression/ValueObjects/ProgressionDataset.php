<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

use App\Contexts\GameWorld\Progression\Enums\ProgressionReleaseStatus;

final readonly class ProgressionDataset
{
    /**
     * @param  array<string,mixed>  $release
     * @param  list<array<string,mixed>>  $heroes
     * @param  array<string,mixed>  $systems
     * @param  list<array<string,mixed>>  $formations
     * @param  array<string,array<string,mixed>>  $catalogues
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
        public array $catalogues = [],
    ) {}

    public function releaseStatus(): ProgressionReleaseStatus
    {
        return ProgressionReleaseStatus::fromRelease($this->release);
    }

    public function isPublished(): bool
    {
        return $this->releaseStatus() === ProgressionReleaseStatus::Published;
    }

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
    public function coverageAssertions(): array
    {
        $items = $this->release['coverage_assertions'] ?? null;
        if (is_array($items)) {
            return array_values(array_filter($items, 'is_array'));
        }

        // The existing immutable corpus already records discovered/canonical/fact
        // counts in family_dispositions. Treat those rows as the release-owned
        // assertions until a later release emits coverage_assertions explicitly.
        return $this->dispositions();
    }

    /** @return list<array<string,mixed>> */
    public function conflicts(): array
    {
        $items = $this->release['conflicts'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function sourceGaps(): array
    {
        $items = $this->release['source_gaps'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @return array<string,mixed>|null */
    public function catalogue(string $family): ?array
    {
        $value = $this->catalogues[$family] ?? null;

        return is_array($value) ? $value : null;
    }

    /** @return list<string> */
    public function catalogueFamilies(): array
    {
        $families = array_keys($this->catalogues);
        sort($families);

        return $families;
    }
}
