<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class ProgressionDatasetQuery
{
    private const DIRECTORY = 'resources/data/progression';

    /** @var list<string> */
    private const REQUIRED_FAMILIES = [
        'heroes',
        'hero_skills',
        'hero_star_shards',
        'hero_exclusive_equipment',
        'hero_gear',
        'governor_gear',
        'governor_charms',
        'formations',
        'buildings',
        'troops',
        'academy_research',
        'war_academy',
        'alliance_tech',
        'pets',
        'masters',
        'max_levels',
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
        $datasets = $this->all();
        if ($datasets === []) {
            throw new RuntimeException('No factual progression dataset is published.');
        }

        usort($datasets, static fn (ProgressionDataset $a, ProgressionDataset $b): int => strcmp($b->datasetVersion, $a->datasetVersion));

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
        $heroFile = $this->json($directory.'/heroes.json');
        $systems = $this->json($directory.'/systems.json');
        $formationFile = $this->json($directory.'/formations.json');

        $id = basename($directory);
        if (($release['id'] ?? null) !== $id
            || ! is_int($release['schema_version'] ?? null)
            || ! is_string($release['dataset_version'] ?? null)
            || ! is_string($release['observed_at'] ?? null)
            || ! is_array($release['sources'] ?? null)
            || ! is_array($release['family_dispositions'] ?? null)
            || ! is_array($heroFile['heroes'] ?? null)
            || ! is_array($heroFile['provenance'] ?? null)
            || ! is_array($formationFile['formations'] ?? null)) {
            throw new RuntimeException('Factual progression dataset does not satisfy schema version 1.');
        }

        $heroes = array_values(array_filter($heroFile['heroes'], 'is_array'));
        $formations = array_values(array_filter($formationFile['formations'], 'is_array'));
        $this->validateHeroes($heroes);
        $this->validateFormations($formations);
        $this->validateDispositions($release);
        $this->validateSources($release, [$heroFile, $systems, $formationFile]);

        $checksumParts = [];
        foreach (['formations.json', 'heroes.json', 'release.json', 'systems.json'] as $file) {
            $raw = file_get_contents($directory.'/'.$file);
            if (! is_string($raw)) {
                throw new RuntimeException('Unable to read factual progression release file.');
            }
            $checksumParts[] = $file.':'.hash('sha256', $raw);
        }

        return new ProgressionDataset(
            id: $id,
            schemaVersion: $release['schema_version'],
            datasetVersion: $release['dataset_version'],
            observedAt: $release['observed_at'],
            checksum: hash('sha256', implode("\n", $checksumParts)),
            release: $release,
            heroes: $heroes,
            systems: $systems,
            formations: $formations,
        );
    }

    /** @return array<string,mixed> */
    private function json(string $path): array
    {
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException('Unable to read factual progression dataset.');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Factual progression dataset contains invalid JSON.', previous: $exception);
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
            if (! is_string($id) || $id === '' || ! is_string($hero['name'] ?? null)
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
            if (! is_string($id) || isset($ids[$id]) || ! is_int($infantry) || ! is_int($cavalry) || ! is_int($archer)
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
        $seen = [];
        foreach ($release['family_dispositions'] as $row) {
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

        foreach (self::REQUIRED_FAMILIES as $family) {
            if (! isset($seen[$family])) {
                throw new RuntimeException('Progression release silently omitted required family: '.$family);
            }
        }
    }

    /**
     * @param array<string,mixed> $release
     * @param list<array<string,mixed>> $documents
     */
    private function validateSources(array $release, array $documents): void
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

        foreach ($documents as $document) {
            $this->validateSourceReferencesRecursively($document, $sourceIds);
        }
    }

    /** @param array<string,mixed> $value @param array<string,true> $sourceIds */
    private function validateSourceReferencesRecursively(array $value, array $sourceIds): void
    {
        foreach ($value as $key => $child) {
            if ($key === 'source_id') {
                if (! is_string($child) || ! isset($sourceIds[$child])) {
                    throw new RuntimeException('Progression fact references an unregistered source.');
                }
                continue;
            }
            if ($key === 'source_ids') {
                if (! is_array($child) || $child === []) {
                    throw new RuntimeException('Progression fact source_ids must be a non-empty list.');
                }
                foreach ($child as $sourceId) {
                    if (! is_string($sourceId) || ! isset($sourceIds[$sourceId])) {
                        throw new RuntimeException('Progression fact references an unregistered source.');
                    }
                }
                continue;
            }
            if (is_array($child)) {
                $this->validateSourceReferencesRecursively($child, $sourceIds);
            }
        }
    }
}
