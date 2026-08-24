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

    /** @var list<string> */
    private const REQUIRED_V2_FAMILIES = [
        'hero_xp',
        'truegold',
        'vip',
        'kvk_scoring',
        'database_reference_tables',
        'progression_event_tables',
    ];

    /** @var list<string> */
    private const REQUIRED_V2_FILES = [
        'academy_research.json',
        'alliance_tech_tables.json',
        'buildings_core.json',
        'buildings_tables.json',
        'database_tables.json',
        'events_tables.json',
        'governor_charms.json',
        'governor_gear.json',
        'hero_shards.json',
        'hero_xp.json',
        'heroes_tables.json',
        'kvk_scoring.json',
        'masters_open.json',
        'masters_tables.json',
        'pets_tables.json',
        'source-lock.json',
        'troops.json',
        'truegold.json',
        'vip.json',
        'war_academy.json',
    ];

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
        $datasets = $this->all();
        if ($datasets === []) {
            throw new RuntimeException('No factual progression dataset is published.');
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
        $heroFile = $this->json($directory.'/heroes.json');
        $systems = $this->json($directory.'/systems.json');
        $formationFile = $this->json($directory.'/formations.json');

        $id = basename($directory);
        $schemaVersion = $release['schema_version'] ?? null;
        if (($release['id'] ?? null) !== $id
            || ! is_int($schemaVersion)
            || ! in_array($schemaVersion, [1, 2], true)
            || ! is_string($release['dataset_version'] ?? null)
            || ! is_string($release['observed_at'] ?? null)
            || ! is_array($release['sources'] ?? null)
            || ! is_array($release['family_dispositions'] ?? null)
            || ! is_array($heroFile['heroes'] ?? null)
            || ! is_array($heroFile['provenance'] ?? null)
            || ! is_array($formationFile['formations'] ?? null)) {
            throw new RuntimeException('Factual progression dataset does not satisfy a supported schema.');
        }

        $files = $this->releaseFiles($release, $schemaVersion);
        $documents = [];
        foreach ($files as $file) {
            $documents[$file] = $this->json($directory.'/'.$file);
        }

        $heroes = array_values(array_filter($heroFile['heroes'], 'is_array'));
        $formations = array_values(array_filter($formationFile['formations'], 'is_array'));
        $catalogues = [];
        foreach ($documents as $file => $document) {
            if (in_array($file, ['heroes.json', 'systems.json', 'formations.json', 'source-lock.json'], true)) {
                continue;
            }
            $catalogues[substr($file, 0, -5)] = $document;
        }

        $this->validateHeroes($heroes);
        $this->validateFormations($formations);
        $this->validateDispositions($release, $schemaVersion);
        $this->validateSources($release, array_values($documents));
        $this->validateNoAdvisoryKeys($documents);
        if ($schemaVersion >= 2) {
            $this->validateSourceLock($documents['source-lock.json'] ?? null);
            $this->validateSourceGaps($release);
            $this->validateDetailedCoverage($catalogues);
        }

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

    /** @param array<string,mixed> $release @return list<string> */
    private function releaseFiles(array $release, int $schemaVersion): array
    {
        if ($schemaVersion === 1) {
            return ['formations.json', 'heroes.json', 'systems.json'];
        }

        $files = $release['files'] ?? null;
        if (! is_array($files) || $files === []) {
            throw new RuntimeException('Progression schema v2 release must declare its immutable files.');
        }

        $normalized = [];
        foreach ($files as $file) {
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

        foreach (['formations.json', 'heroes.json', 'systems.json', ...self::REQUIRED_V2_FILES] as $required) {
            if (! isset($normalized[$required])) {
                throw new RuntimeException('Progression schema v2 release omitted required file: '.$required);
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
    private function validateDispositions(array $release, int $schemaVersion): void
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
            if ($schemaVersion >= 2 && $row['status'] === 'indexed_external_table') {
                throw new RuntimeException('Schema v2 cannot leave reusable complete data as index-only.');
            }
            $seen[$row['family']] = true;
        }

        $required = [...self::REQUIRED_FAMILIES, ...($schemaVersion >= 2 ? self::REQUIRED_V2_FAMILIES : [])];
        foreach ($required as $family) {
            if (! isset($seen[$family])) {
                throw new RuntimeException('Progression release silently omitted required family: '.$family);
            }
        }
    }

    /** @param array<string,mixed> $release @param list<array<string,mixed>> $documents */
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

        $this->validateSourceReferencesRecursively($release, $sourceIds);
        foreach ($documents as $document) {
            $this->validateSourceReferencesRecursively($document, $sourceIds);
        }
    }

    /** @param array<mixed> $value @param array<string,true> $sourceIds */
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

    /** @param array<string,array<string,mixed>> $documents */
    private function validateNoAdvisoryKeys(array $documents): void
    {
        foreach ($documents as $file => $document) {
            $this->validateNoAdvisoryKeysRecursively($document, $file);
        }
    }

    /** @param array<mixed> $value */
    private function validateNoAdvisoryKeysRecursively(array $value, string $path): void
    {
        foreach ($value as $key => $child) {
            if (is_string($key) && in_array(mb_strtolower($key), self::ADVISORY_KEYS, true)) {
                throw new RuntimeException('Advisory field leaked into factual progression release: '.$path.'.'.$key);
            }
            if (is_array($child)) {
                $this->validateNoAdvisoryKeysRecursively($child, $path.'.'.(string) $key);
            }
        }
    }

    /** @param array<string,mixed>|null $sourceLock */
    private function validateSourceLock(?array $sourceLock): void
    {
        if ($sourceLock === null || ! is_array($sourceLock['sources'] ?? null) || $sourceLock['sources'] === []) {
            throw new RuntimeException('Progression schema v2 requires a non-empty source lock.');
        }

        foreach ($sourceLock['sources'] as $row) {
            if (! is_array($row)
                || ! is_string($row['source_id'] ?? null)
                || ! is_string($row['url'] ?? null)
                || filter_var($row['url'], FILTER_VALIDATE_URL) === false
                || ! is_string($row['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $row['sha256']) !== 1) {
                throw new RuntimeException('Progression source lock row is invalid.');
            }
        }
    }

    /** @param array<string,mixed> $release */
    private function validateSourceGaps(array $release): void
    {
        $gaps = $release['source_gaps'] ?? null;
        if (! is_array($gaps)) {
            throw new RuntimeException('Progression schema v2 requires explicit source-gap reporting.');
        }

        if (($release['dataset_version'] ?? null) !== '2026.08.23.2') {
            return;
        }

        if (count($gaps) !== 1 || ! is_array($gaps[0] ?? null)) {
            throw new RuntimeException('Progression 2026.08.23.2 must expose exactly one reviewed source gap.');
        }
        $gap = $gaps[0];
        if (($gap['id'] ?? null) !== 'academy-fortified-mail-vi-level-table'
            || ($gap['family'] ?? null) !== 'academy_research'
            || ($gap['source_id'] ?? null) !== 'kingshotdata'
            || ($gap['entity'] ?? null) !== 'Fortified Mail VI'
            || ($gap['declared_max_level'] ?? null) !== 6
            || ($gap['missing_visible_level_rows'] ?? null) !== 6
            || ($gap['status'] ?? null) !== 'source_table_missing') {
            throw new RuntimeException('Progression 2026.08.23.2 source gap does not match reviewed source evidence.');
        }
    }

    /** @param array<string,array<string,mixed>> $catalogues */
    private function validateDetailedCoverage(array $catalogues): void
    {
        $academy = $catalogues['academy_research'] ?? null;
        if (! is_array($academy)
            || ($academy['declared_technologies'] ?? null) !== 191
            || ($academy['visible_level_rows'] ?? null) !== 714
            || ($academy['declared_max_level_sum'] ?? null) !== 720
            || ! is_array($academy['source_table_gaps'] ?? null)
            || count($academy['source_table_gaps']) !== 1
            || ! is_array($academy['technologies'] ?? null)
            || count($academy['technologies']) !== 191) {
            throw new RuntimeException('Academy progression release is incomplete.');
        }

        $levels = 0;
        $ids = [];
        $fortifiedMail = null;
        foreach ($academy['technologies'] as $technology) {
            if (! is_array($technology)
                || ! is_string($technology['id'] ?? null)
                || isset($ids[$technology['id']])
                || ! is_int($technology['max_level'] ?? null)
                || ! is_string($technology['levels_status'] ?? null)
                || ! is_array($technology['levels'] ?? null)) {
                throw new RuntimeException('Academy technology row is invalid.');
            }
            $ids[$technology['id']] = true;
            $levels += count($technology['levels']);
            if (($technology['name'] ?? null) === 'Fortified Mail VI') {
                $fortifiedMail = $technology;
            }
        }
        if ($levels !== 714
            || ! is_array($fortifiedMail)
            || ($fortifiedMail['max_level'] ?? null) !== 6
            || ($fortifiedMail['levels_status'] ?? null) !== 'source_table_missing'
            || ($fortifiedMail['levels'] ?? null) !== []) {
            throw new RuntimeException('Academy level/source-gap coverage is incomplete.');
        }

        $alliance = $catalogues['alliance_tech_tables'] ?? null;
        if (! is_array($alliance)
            || ($alliance['declared_technologies'] ?? null) !== 60
            || ($alliance['visible_level_rows'] ?? null) !== 279
            || ! is_array($alliance['technologies'] ?? null)
            || count($alliance['technologies']) !== 60) {
            throw new RuntimeException('Alliance Technology progression release is incomplete.');
        }
        $allianceLevels = 0;
        foreach ($alliance['technologies'] as $technology) {
            if (! is_array($technology) || ! is_array($technology['levels'] ?? null)) {
                throw new RuntimeException('Alliance Technology row is invalid.');
            }
            $allianceLevels += count($technology['levels']);
        }
        if ($allianceLevels !== 279) {
            throw new RuntimeException('Alliance Technology level coverage is incomplete.');
        }

        foreach ([
            'buildings_tables' => 12,
            'pets_tables' => 14,
            'masters_tables' => 6,
            'heroes_tables' => 34,
            'database_tables' => 8,
            'events_tables' => 33,
        ] as $family => $expectedPages) {
            $document = $catalogues[$family] ?? null;
            if (! is_array($document)
                || ($document['discovered_pages'] ?? null) !== $expectedPages
                || ! is_array($document['pages'] ?? null)
                || count($document['pages']) !== $expectedPages) {
                throw new RuntimeException('Progression confirmed source page coverage mismatch: '.$family);
            }
        }

        foreach (['governor_gear' => 58, 'war_academy' => 30] as $family => $expected) {
            $document = $catalogues[$family] ?? null;
            if (! is_array($document)
                || ! is_array($document['source_meta'] ?? null)
                || ($document['source_meta']['count'] ?? null) !== $expected) {
                throw new RuntimeException('Progression detailed family count mismatch: '.$family);
            }
        }

        $heroXp = $catalogues['hero_xp'] ?? null;
        if (! is_array($heroXp) || ! is_array($heroXp['source_meta'] ?? null)) {
            throw new RuntimeException('Hero XP detailed family is unavailable.');
        }
        $heroXpCount = $heroXp['source_meta']['count'] ?? null;
        if (is_int($heroXpCount) && $heroXpCount < 80) {
            throw new RuntimeException('Hero XP detailed family is incomplete.');
        }
    }
}
