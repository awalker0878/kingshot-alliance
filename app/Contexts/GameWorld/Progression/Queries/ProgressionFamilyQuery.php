<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Queries;

use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use Illuminate\Validation\ValidationException;

final class ProgressionFamilyQuery
{
    private const PER_PAGE = 40;

    /**
     * @return array{
     *   family:string,
     *   columns:list<string>,
     *   rows:list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}>,
     *   page:int,
     *   perPage:int,
     *   total:int,
     *   lastPage:int,
     *   sourceMeta:array<string,mixed>|null
     * }
     */
    public function page(
        ProgressionDataset $dataset,
        string $family,
        string $query = '',
        int $page = 1,
    ): array {
        $family = trim($family);
        $allowed = ['heroes', 'hero_skills', 'formations', ...$dataset->catalogueFamilies()];
        if (! in_array($family, $allowed, true)) {
            throw ValidationException::withMessages(['family' => 'The selected progression family is unavailable in this dataset release.']);
        }

        [$rows, $sourceMeta] = match ($family) {
            'heroes' => [$this->heroRows($dataset), null],
            'hero_skills' => [$this->heroSkillRows($dataset), null],
            'formations' => [$this->formationRows($dataset), null],
            default => $this->catalogueRows($dataset, $family),
        };

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_contains(
                    mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                    $query,
                ),
            ));
        }

        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row['values']) as $column) {
                if (! in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'family' => $family,
            'columns' => $columns,
            'rows' => array_slice($rows, $offset, self::PER_PAGE),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'lastPage' => $lastPage,
            'sourceMeta' => $sourceMeta,
        ];
    }

    /** @return list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}> */
    private function heroRows(ProgressionDataset $dataset): array
    {
        return array_map(static function (array $hero): array {
            $unlockMethods = is_array($hero['unlock_methods'] ?? null)
                ? implode(' · ', array_map('strval', $hero['unlock_methods']))
                : null;

            return [
                'path' => (string) ($hero['id'] ?? ''),
                'values' => [
                    'Hero' => (string) ($hero['name'] ?? ''),
                    'Rarity' => (string) ($hero['rarity'] ?? ''),
                    'Troop class' => (string) ($hero['troop_class'] ?? ''),
                    'Generation' => isset($hero['generation']) ? (string) $hero['generation'] : null,
                    'Typical unlock day' => isset($hero['typical_unlock_day']) ? (string) $hero['typical_unlock_day'] : null,
                    'Acquisition' => $unlockMethods,
                    'Skills captured' => is_array($hero['skills'] ?? null) ? (string) count($hero['skills']) : '0',
                    'Skill evidence' => is_string($hero['skill_source_status'] ?? null) ? $hero['skill_source_status'] : null,
                ],
                'sourceIds' => $this->stringList($hero['source_ids'] ?? []),
                'confidence' => is_string($hero['skill_source_status'] ?? null) ? $hero['skill_source_status'] : null,
            ];
        }, $dataset->heroes);
    }

    /** @return list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}> */
    private function heroSkillRows(ProgressionDataset $dataset): array
    {
        $rows = [];
        foreach ($dataset->heroes as $hero) {
            $skills = $hero['skills'] ?? [];
            if (! is_array($skills)) {
                continue;
            }
            foreach ($skills as $index => $skill) {
                if (! is_array($skill)) {
                    continue;
                }
                $values = [
                    'Hero' => (string) ($hero['name'] ?? ''),
                    'Skill' => is_string($skill['name'] ?? null) ? $skill['name'] : 'Unknown',
                ];
                $this->flattenScalarValues($skill['effects'] ?? [], 'Effect', $values);
                $this->flattenScalarValues($skill['upgrade_preview'] ?? [], 'Levels', $values);
                $rows[] = [
                    'path' => (string) ($hero['id'] ?? '').'.skill.'.($index + 1),
                    'values' => $values,
                    'sourceIds' => $this->stringList($hero['source_ids'] ?? []),
                    'confidence' => is_string($hero['skill_source_status'] ?? null) ? $hero['skill_source_status'] : null,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}> */
    private function formationRows(ProgressionDataset $dataset): array
    {
        return array_map(function (array $formation): array {
            return [
                'path' => (string) ($formation['id'] ?? ''),
                'values' => [
                    'Formation' => (string) ($formation['name'] ?? ''),
                    'Infantry %' => isset($formation['infantry']) ? (string) $formation['infantry'] : null,
                    'Cavalry %' => isset($formation['cavalry']) ? (string) $formation['cavalry'] : null,
                    'Archer %' => isset($formation['archer']) ? (string) $formation['archer'] : null,
                    'Mode' => is_string($formation['mode'] ?? null) ? $formation['mode'] : null,
                    'Scope' => is_string($formation['scope'] ?? null) ? $formation['scope'] : null,
                    'Evidence' => is_string($formation['evidence_status'] ?? null) ? $formation['evidence_status'] : null,
                ],
                'sourceIds' => $this->stringList($formation['source_ids'] ?? []),
                'confidence' => is_string($formation['evidence_status'] ?? null) ? $formation['evidence_status'] : null,
            ];
        }, $dataset->formations);
    }

    /**
     * @return array{
     *   0:list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}>,
     *   1:array<string,mixed>|null
     * }
     */
    private function catalogueRows(ProgressionDataset $dataset, string $family): array
    {
        $document = $dataset->catalogue($family);
        if ($document === null) {
            return [[], null];
        }

        $sourceMeta = is_array($document['source_meta'] ?? null) ? $document['source_meta'] : null;
        $sourceIds = [];
        if (is_string($document['source_id'] ?? null)) {
            $sourceIds[] = $document['source_id'];
        }
        $confidence = $sourceMeta !== null && is_string($sourceMeta['confidence'] ?? null)
            ? $sourceMeta['confidence']
            : null;

        if ($family === 'academy_research') {
            return [$this->academyRows($document), $sourceMeta];
        }

        if (is_array($document['pages'] ?? null)) {
            $rows = [];
            foreach ($document['pages'] as $page) {
                if (! is_array($page) || ! is_array($page['tables'] ?? null)) {
                    continue;
                }
                foreach ($page['tables'] as $tableIndex => $table) {
                    if (! is_array($table) || ! is_array($table['rows'] ?? null)) {
                        continue;
                    }
                    foreach ($table['rows'] as $rowIndex => $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $values = [
                            'Entity' => is_string($page['name'] ?? null) ? $page['name'] : null,
                            'Table' => is_string($table['heading'] ?? null) ? $table['heading'] : null,
                        ];
                        foreach ($row as $key => $value) {
                            if (is_scalar($value) || $value === null) {
                                $values[(string) $key] = $value === null ? null : (string) $value;
                            }
                        }
                        $rows[] = [
                            'path' => (string) ($page['id'] ?? 'page').'.'.($tableIndex + 1).'.'.($rowIndex + 1),
                            'values' => $values,
                            'sourceIds' => is_string($document['source_id'] ?? null) ? [$document['source_id']] : [],
                            'confidence' => null,
                        ];
                    }
                }
            }

            return [$rows, $sourceMeta];
        }

        $rows = [];
        $this->flattenDocumentRows(
            $document['data'] ?? $document,
            $family,
            $rows,
            $sourceIds,
            $confidence,
        );

        return [$rows, $sourceMeta];
    }

    /** @return list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}> */
    private function academyRows(array $document): array
    {
        $rows = [];
        foreach ($document['technologies'] ?? [] as $technology) {
            if (! is_array($technology) || ! is_array($technology['levels'] ?? null)) {
                continue;
            }
            foreach ($technology['levels'] as $index => $level) {
                if (! is_array($level)) {
                    continue;
                }
                $values = [
                    'Tree' => is_string($technology['tree'] ?? null) ? $technology['tree'] : null,
                    'Technology' => is_string($technology['name'] ?? null) ? $technology['name'] : null,
                ];
                foreach ($level as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $values[(string) $key] = $value === null ? null : (string) $value;
                    }
                }
                $rows[] = [
                    'path' => (string) ($technology['id'] ?? 'technology').'.level.'.($index + 1),
                    'values' => $values,
                    'sourceIds' => is_string($technology['source_id'] ?? null) ? [$technology['source_id']] : [],
                    'confidence' => null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param mixed $value
     * @param list<array{path:string,values:array<string,string|null>,sourceIds:list<string>,confidence:string|null}> $rows
     * @param list<string> $sourceIds
     */
    private function flattenDocumentRows(
        mixed $value,
        string $path,
        array &$rows,
        array $sourceIds,
        ?string $confidence,
    ): void {
        if (! is_array($value)) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $index => $child) {
                if (is_array($child)) {
                    $this->flattenDocumentRows($child, $path.'.'.($index + 1), $rows, $sourceIds, $confidence);
                }
            }

            return;
        }

        $values = [];
        $nested = [];
        foreach ($value as $key => $child) {
            if (is_scalar($child) || $child === null) {
                $values[(string) $key] = $child === null ? null : (string) $child;
                continue;
            }
            if (is_array($child) && $this->isScalarList($child)) {
                $values[(string) $key] = implode(' · ', array_map(static fn (mixed $item): string => (string) $item, $child));
                continue;
            }
            if (is_array($child) && ! array_is_list($child) && $this->containsOnlyScalarLeaves($child)) {
                $this->flattenScalarValues($child, (string) $key, $values);
                continue;
            }
            if (is_array($child)) {
                $nested[(string) $key] = $child;
            }
        }

        if ($values !== []) {
            $rows[] = [
                'path' => $path,
                'values' => $values,
                'sourceIds' => $sourceIds,
                'confidence' => $confidence,
            ];
        }
        foreach ($nested as $key => $child) {
            $this->flattenDocumentRows($child, $path.'.'.$key, $rows, $sourceIds, $confidence);
        }
    }

    /** @param mixed $value */
    private function isScalarList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $value */
    private function containsOnlyScalarLeaves(array $value): bool
    {
        foreach ($value as $child) {
            if (is_array($child)) {
                if (array_is_list($child) ? ! $this->isScalarList($child) : ! $this->containsOnlyScalarLeaves($child)) {
                    return false;
                }
                continue;
            }
            if (! is_scalar($child) && $child !== null) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $value @param array<string,string|null> $target */
    private function flattenScalarValues(mixed $value, string $prefix, array &$target): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            $label = $prefix.' · '.str_replace('_', ' ', (string) $key);
            if (is_scalar($child) || $child === null) {
                $target[$label] = $child === null ? null : (string) $child;
                continue;
            }
            if ($this->isScalarList($child)) {
                $target[$label] = implode(' · ', array_map(static fn (mixed $item): string => (string) $item, $child));
                continue;
            }
            if (is_array($child)) {
                $this->flattenScalarValues($child, $label, $target);
            }
        }
    }

    /** @param mixed $value @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
