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
            || ! is_array($formationFile['formations'] ?? null)) {
            throw new RuntimeException('Factual progression dataset does not satisfy schema version 1.');
        }

        $heroes = array_values(array_filter($heroFile['heroes'], 'is_array'));
        $formations = array_values(array_filter($formationFile['formations'], 'is_array'));
        $this->validateHeroes($heroes);
        $this->validateFormations($formations);
        $this->validateSources($release, $heroes, $systems, $formations);

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
                || ! is_int($hero['generation'] ?? null)) {
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
                || ($formation['evidence_status'] ?? null) !== 'community_convention') {
                throw new RuntimeException('Formation convention row is invalid.');
            }
            $ids[$id] = true;
        }
    }

    /**
     * @param array<string,mixed> $release
     * @param list<array<string,mixed>> $heroes
     * @param array<string,mixed> $systems
     * @param list<array<string,mixed>> $formations
     */
    private function validateSources(array $release, array $heroes, array $systems, array $formations): void
    {
        $sourceIds = [];
        foreach ($release['sources'] as $source) {
            if (! is_array($source) || ! is_string($source['id'] ?? null) || ! is_string($source['uri'] ?? null)
                || ! is_string($source['retrieved_at'] ?? null) || ! is_string($source['authority_tier'] ?? null)) {
                throw new RuntimeException('Progression source registry row is invalid.');
            }
            $sourceIds[$source['id']] = true;
        }

        $encoded = json_encode([$heroes, $systems, $formations], JSON_THROW_ON_ERROR);
        preg_match_all('/"source_ids?"\s*:\s*(?:\[([^\]]*)\]|"([^"]+)")/', $encoded, $matches);
        foreach (array_merge($matches[1] ?? [], $matches[2] ?? []) as $match) {
            preg_match_all('/"([^"]+)"/', (string) $match, $ids);
            foreach ($ids[1] ?? [] as $sourceId) {
                if (! isset($sourceIds[$sourceId])) {
                    throw new RuntimeException('Progression fact references an unregistered source.');
                }
            }
        }
    }
}
