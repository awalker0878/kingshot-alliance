<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\Enums\ProgressionReleaseStatus;
use App\Contexts\GameWorld\Progression\Exceptions\NoProgressionDatasetPublished;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class ProgressionDatasetQuery
{
    private const DIRECTORY = 'resources/data/progression';

    /** @var list<string> */
    private const BASE_FILES = ['formations.json', 'heroes.json', 'systems.json'];

    /** @var list<string> */
    private const ADVISORY_KEYS = [
        'tier_list',
        'tierlist',
        'recommended',
        'recommendation',
        'recommendations',
        'investment_priority',
        'upgrade_priority',
        'priority_score',
        'optimizer',
        'optimization',
        'best_use',
        'best_for',
        'f2p_rating',
        'value_rating',
        'longevity_rating',
    ];

    /** @return list<ProgressionDataset> */
    public function all(): array
    {
        $paths = glob(base_path(self::DIRECTORY.'/*/release.json')) ?: [];
        sort($paths);

        return array_map(fn (string $path): ProgressionDataset => $this->load(dirname($path)), $paths);
    }

    public function latest(): ProgressionDataset
    {
        $datasets = array_values(array_filter(
            $this->all(),
            static fn (ProgressionDataset $dataset): bool => $dataset->releaseStatus() === ProgressionReleaseStatus::Published,
        ));
        if ($datasets === []) {
            throw new NoProgressionDatasetPublished('No published factual progression dataset is available.');
        }

        usort(
            $datasets,
            static fn (ProgressionDataset $a, ProgressionDataset $b): int => version_compare($b->datasetVersion, $a->datasetVersion),
        );

        return $datasets[0];
    }

    public function require(string $datasetId, ?string $expectedChecksum = null): ProgressionDataset
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,119}$/', $datasetId) !== 1) {
            throw ValidationException::withMessages(['progression_dataset_id' => 'The selected progression dataset is invalid.']);
        }

        $directory = base_path(self::DIRECTORY.'/'.$datasetId);
        if (! is_dir($directory)) {
            throw ValidationException::withMessages(['progression_dataset_id' => 'The selected progression dataset is unavailable.']);
        }

        $dataset = $this->load($directory);
        if ($expectedChecksum !== null && ! hash_equals($dataset->checksum, $expectedChecksum)) {
            throw ValidationException::withMessages(['progression_dataset_id' => 'The selected progression dataset changed and must be reloaded.']);
        }

        return $dataset;
    }

    public function canonicalHeroId(string $value, ?ProgressionDataset $dataset = null): ?string
    {
        $dataset ??= $this->latest();
        $needle = mb_strtolower(trim($value));
        foreach ($dataset->heroes as $hero) {
            $id = is_string($hero['id'] ?? null) ? $hero['id'] : '';
            $name = is_string($hero['name'] ?? null) ? $hero['name'] : '';
            if ($needle === mb_strtolower($id) || $needle === mb_strtolower($name)) {
                return $id;
            }
        }

        return null;
    }

    private function load(string $directory): ProgressionDataset
    {
        $release = $this->json($directory.'/release.json');
        $id = basename($directory);
        $schemaVersion = $release['schema_version'] ?? null;
        if (($release['id'] ?? null) !== $id
            || ! is_int($schemaVersion)
            || ! in_array($schemaVersion, [1, 2], true)
            || ! is_string($release['dataset_version'] ?? null)
            || ! is_string($release['observed_at'] ?? null)
            || ! is_array($release['sources'] ?? null)
            || ! is_array($release['family_dispositions'] ?? null)) {
            throw new RuntimeException('Factual progression dataset does not satisfy a supported release schema.');
        }
        if (isset($release['release_status'])
            && (! is_string($release['release_status']) || ProgressionReleaseStatus::tryFrom($release['release_status']) === null)) {
            throw new RuntimeException('Progression release_status is invalid.');
        }

        $files = $this->releaseFiles($release, $schemaVersion);
        $documents = [];
        foreach ($files as $file) {
            $documents[$file] = $this->json($directory.'/'.$file);
        }

        $heroFile = $documents['heroes.json'] ?? null;
        $systems = $documents['systems.json'] ?? null;
        $formationFile = $documents['formations.json'] ?? null;
        if (! is_array($heroFile)
            || ! is_array($heroFile['heroes'] ?? null)
            || ! is_array($heroFile['provenance'] ?? null)
            || ! is_array($systems)
            || ! is_array($formationFile)
            || ! is_array($formationFile['formations'] ?? null)) {
            throw new RuntimeException('Progression release is missing a valid base catalogue document.');
        }

        $heroes = array_values(array_filter($heroFile['heroes'], 'is_array'));
        $formations = array_values(array_filter($formationFile['formations'], 'is_array'));
        $catalogues = [];
        foreach ($documents as $file => $document) {
            if (in_array($file, [...self::BASE_FILES, 'source-lock.json'], true)) {
                continue;
            }
            $catalogues[substr($file, 0, -5)] = $document;
        }

        $this->validateHeroes($heroes);
        $this->validateFormations($formations);
        $this->validateDispositions($release);
        $this->validateCoverageAssertions($release, $documents);
        $sourceIds = $this->validateSources($release, array_values($documents));
        $this->validateNoAdvisoryKeys($documents);
        if (isset($documents['source-lock.json'])) {
            $this->validateSourceLock($documents['source-lock.json'], $sourceIds);
        }
        $this->validateSourceGaps($release, $sourceIds);

        $checksumFiles = array_values(array_unique(['release.json', ...$files]));
        sort($checksumFiles);
        $checksumParts = [];
        foreach ($checksumFiles as $file) {
            $raw = file_get_contents($directory.'/'.$file);
            if (! is_string($raw)) {
                throw new RuntimeException('Unable to read factual progression release file: '.$file);
            }
            $checksumParts[] = $file.':'.hash('sha256', $raw);
        }

        return new ProgressionDataset(
            id: $id,
            schemaVersion: $schemaVersion,
            datasetVersion: $release['dataset_version'],
            observedAt: $release['observed_at'],
            checksum: hash('sha256', implode("\n", $checksumParts)),
            release: $release,
            heroes: $heroes,
            systems: $systems,
            formations: $formations,
            catalogues: $catalogues,
        );
    }

    /** @param array<string,mixed> $release
     * @return list<string>
     */
    private function releaseFiles(array $release, int $schemaVersion): array
    {
        if ($schemaVersion === 1) {
            return self::BASE_FILES;
        }

        $declared = $release['files'] ?? null;
        if (! is_array($declared) || $declared === []) {
            throw new RuntimeException('Progression schema v2 release must declare its immutable files.');
        }

        $normalized = [];
        foreach ($declared as $file) {
            if (! is_string($file)
                || preg_match('/^[a-z0-9][a-z0-9._-]*\.json$/', $file) !== 1
                || $file === 'release.json') {
                throw new RuntimeException('Progression release declares an invalid file name.');
            }
            if (isset($normalized[$file])) {
                throw new RuntimeException('Progression release file manifest contains duplicates.');
            }
            $normalized[$file] = true;
        }
        foreach (self::BASE_FILES as $required) {
            if (! isset($normalized[$required])) {
                throw new RuntimeException('Progression release omitted base file: '.$required);
            }
        }

        $files = array_keys($normalized);
        sort($files);

        return $files;
    }

    /** @return array<string,mixed> */
    private function json(string $path): array
    {
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException('Unable to read factual progression dataset file: '.basename($path));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Factual progression dataset contains invalid JSON: '.basename($path), previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('Factual progression dataset root must be an object.');
        }

        return $decoded;
    }

    /** @param list<array<string,mixed>> $heroes */
    private function validateHeroes(array $heroes): void
    {
        $ids = [];
        foreach ($heroes as $hero) {
            $id = $hero['id'] ?? null;
            if (! is_string($id) || $id === ''
                || ! is_string($hero['name'] ?? null)
                || ! in_array($hero['rarity'] ?? null, ['Rare', 'Epic', 'Legendary'], true)
                || ! in_array($hero['troop_class'] ?? null, ['Infantry', 'Cavalry', 'Archer'], true)
                || ! is_int($hero['generation'] ?? null)
                || ! is_int($hero['typical_unlock_day'] ?? null)) {
                throw new RuntimeException('Hero catalogue row is invalid.');
            }
            if (isset($ids[$id])) {
                throw new RuntimeException('Hero catalogue IDs must be unique.');
            }
            $ids[$id] = true;
        }
    }

    /** @param list<array<string,mixed>> $formations */
    private function validateFormations(array $formations): void
    {
        $ids = [];
        foreach ($formations as $formation) {
            $id = $formation['id'] ?? null;
            $infantry = $formation['infantry'] ?? null;
            $cavalry = $formation['cavalry'] ?? null;
            $archer = $formation['archer'] ?? null;
            if (! is_string($id) || $id === '' || isset($ids[$id])
                || ! is_int($infantry) || ! is_int($cavalry) || ! is_int($archer)
                || min($infantry, $cavalry, $archer) < 0 || max($infantry, $cavalry, $archer) > 100
                || $infantry + $cavalry + $archer !== 100
                || ($formation['evidence_status'] ?? null) !== 'community_convention'
                || ! is_array($formation['source_ids'] ?? null)
                || $formation['source_ids'] === []) {
                throw new RuntimeException('Formation convention row is invalid.');
            }
            foreach (['best', 'recommended', 'score', 'optimization_score'] as $forbidden) {
                if (array_key_exists($forbidden, $formation)) {
                    throw new RuntimeException('Formation convention cannot contain recommendation semantics.');
                }
            }
            $ids[$id] = true;
        }
    }

    /** @param array<string,mixed> $release */
    private function validateDispositions(array $release): void
    {
        $rows = $release['family_dispositions'];
        if ($rows === []) {
            throw new RuntimeException('Progression release must disposition its discovered factual families.');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_string($row['family'] ?? null)
                || ! is_string($row['status'] ?? null)
                || ! is_int($row['discovered_entities'] ?? null)
                || ! is_int($row['canonical_entities'] ?? null)
                || ! is_string($row['reason'] ?? null)
                || $row['discovered_entities'] < 0
                || $row['canonical_entities'] < 0
                || $row['canonical_entities'] > $row['discovered_entities']) {
                throw new RuntimeException('Progression family disposition is invalid.');
            }
            if (isset($seen[$row['family']])) {
                throw new RuntimeException('Progression family dispositions must be unique.');
            }
            $seen[$row['family']] = true;
        }
    }

    /**
     * @param  array<string,mixed>  $release
     * @param  array<string,array<string,mixed>>  $documents
     */
    private function validateCoverageAssertions(array $release, array $documents): void
    {
        $assertions = $release['coverage_assertions'] ?? $release['family_dispositions'];
        if (! is_array($assertions) || $assertions === []) {
            throw new RuntimeException('Progression release must declare machine-readable coverage assertions.');
        }

        $seen = [];
        foreach ($assertions as $assertion) {
            if (! is_array($assertion) || ! is_string($assertion['family'] ?? null) || $assertion['family'] === '') {
                throw new RuntimeException('Progression coverage assertion is invalid.');
            }
            $family = $assertion['family'];
            if (isset($seen[$family])) {
                throw new RuntimeException('Progression coverage assertions must be unique by family.');
            }
            $seen[$family] = true;
            foreach (['discovered_entities', 'canonical_entities', 'facts_imported', 'unresolved_level_tables'] as $countKey) {
                if (array_key_exists($countKey, $assertion)
                    && (! is_int($assertion[$countKey]) || $assertion[$countKey] < 0)) {
                    throw new RuntimeException('Progression coverage assertion count is invalid: '.$family.'.'.$countKey);
                }
            }
            if (isset($assertion['discovered_entities'], $assertion['canonical_entities'])
                && $assertion['canonical_entities'] > $assertion['discovered_entities']) {
                throw new RuntimeException('Progression coverage assertion canonical count exceeds discovered count: '.$family);
            }
            if (isset($assertion['file'])) {
                $file = $assertion['file'];
                if (! is_string($file) || ! isset($documents[$file])) {
                    throw new RuntimeException('Progression coverage assertion references an unavailable release file: '.$family);
                }
            }
        }
    }

    /**
     * @param  array<string,mixed>  $release
     * @param  list<array<string,mixed>>  $documents
     * @return array<string,true>
     */
    private function validateSources(array $release, array $documents): array
    {
        $sourceIds = [];
        foreach ($release['sources'] as $source) {
            if (! is_array($source)
                || ! is_string($source['id'] ?? null)
                || ! is_string($source['label'] ?? null)
                || ! is_string($source['uri'] ?? null)
                || filter_var($source['uri'], FILTER_VALIDATE_URL) === false
                || ! is_string($source['retrieved_at'] ?? null)
                || ! is_string($source['observed_at'] ?? null)
                || ! in_array($source['authority_tier'] ?? null, ['A', 'B', 'C', 'D'], true)
                || ! is_string($source['license_note'] ?? null)) {
                throw new RuntimeException('Progression source registry row is invalid.');
            }
            if (isset($sourceIds[$source['id']])) {
                throw new RuntimeException('Progression source registry IDs must be unique.');
            }
            $sourceIds[$source['id']] = true;
        }

        $this->validateSourceReferencesRecursively($release, $sourceIds);
        foreach ($documents as $document) {
            $this->validateSourceReferencesRecursively($document, $sourceIds);
        }

        return $sourceIds;
    }

    /** @param array<mixed> $value
     * @param  array<string,true>  $sourceIds
     */
    private function validateSourceReferencesRecursively(array $value, array $sourceIds): void
    {
        foreach ($value as $key => $child) {
            if ($key === 'source_id') {
                if (! is_string($child) || ! isset($sourceIds[$child])) {
                    throw new RuntimeException('Progression document references an unknown source_id.');
                }

                continue;
            }
            if ($key === 'source_ids') {
                if (! is_array($child)) {
                    throw new RuntimeException('Progression source_ids reference must be an array.');
                }
                foreach ($child as $sourceId) {
                    if (! is_string($sourceId) || ! isset($sourceIds[$sourceId])) {
                        throw new RuntimeException('Progression document references an unknown source_ids value.');
                    }
                }

                continue;
            }
            if (is_array($child)) {
                $this->validateSourceReferencesRecursively($child, $sourceIds);
            }
        }
    }

    /** @param array<string,array<string,mixed>> $documents */
    private function validateNoAdvisoryKeys(array $documents): void
    {
        foreach ($documents as $document) {
            $this->assertNoAdvisoryKeys($document);
        }
    }

    /** @param array<mixed> $value */
    private function assertNoAdvisoryKeys(array $value): void
    {
        foreach ($value as $key => $child) {
            if (is_string($key) && in_array(mb_strtolower($key), self::ADVISORY_KEYS, true)) {
                throw new RuntimeException('Progression factual release contains prohibited recommendation semantics: '.$key);
            }
            if (is_array($child)) {
                $this->assertNoAdvisoryKeys($child);
            }
        }
    }

    /** @param array<string,mixed> $sourceLock
     * @param  array<string,true>  $sourceIds
     */
    private function validateSourceLock(array $sourceLock, array $sourceIds): void
    {
        if (! is_int($sourceLock['schema_version'] ?? null)
            || ! is_string($sourceLock['observed_at'] ?? null)
            || ! is_array($sourceLock['sources'] ?? null)
            || $sourceLock['sources'] === []) {
            throw new RuntimeException('Progression source-lock document is invalid.');
        }
        foreach ($sourceLock['sources'] as $row) {
            if (! is_array($row)
                || ! is_string($row['source_id'] ?? null)
                || ! isset($sourceIds[$row['source_id']])
                || ! is_string($row['url'] ?? null)
                || filter_var($row['url'], FILTER_VALIDATE_URL) === false
                || ! is_string($row['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $row['sha256']) !== 1) {
                throw new RuntimeException('Progression source-lock row is invalid.');
            }
        }
    }

    /** @param array<string,mixed> $release
     * @param  array<string,true>  $sourceIds
     */
    private function validateSourceGaps(array $release, array $sourceIds): void
    {
        $gaps = $release['source_gaps'] ?? [];
        if (! is_array($gaps)) {
            throw new RuntimeException('Progression source_gaps must be an array.');
        }
        $seen = [];
        foreach ($gaps as $gap) {
            if (! is_array($gap)
                || ! is_string($gap['id'] ?? null)
                || ! is_string($gap['family'] ?? null)
                || ! is_string($gap['source_id'] ?? null)
                || ! isset($sourceIds[$gap['source_id']])
                || ! is_string($gap['status'] ?? null)
                || ! is_string($gap['resolution'] ?? null)) {
                throw new RuntimeException('Progression source gap is invalid.');
            }
            if (isset($seen[$gap['id']])) {
                throw new RuntimeException('Progression source gap IDs must be unique.');
            }
            $seen[$gap['id']] = true;
        }
    }
}
