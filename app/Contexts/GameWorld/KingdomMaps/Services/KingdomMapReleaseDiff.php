<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;

final class KingdomMapReleaseDiff
{
    /**
     * @return array<string,array{added:list<string>,removed:list<string>,changed:list<string>}>
     */
    public function between(KingdomMapDataset $from, KingdomMapDataset $to): array
    {
        $sections = [
            'sources' => 'sources',
            'object_types' => 'object_types',
            'zones' => 'zones',
            'structures' => 'structures',
            'facilities' => 'facilities',
            'placement_rules' => 'placement_rules',
            'resource_layers' => 'resource_layers',
            'artifacts' => 'artifacts',
        ];

        $result = [];
        foreach ($sections as $resultKey => $dataKey) {
            $result[$resultKey] = $this->section(
                $this->keyed($from->data[$dataKey] ?? []),
                $this->keyed($to->data[$dataKey] ?? []),
            );
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $from
     * @param  array<string,mixed>  $to
     * @return array{added:list<string>,removed:list<string>,changed:list<string>}
     */
    private function section(array $from, array $to): array
    {
        $fromKeys = array_keys($from);
        $toKeys = array_keys($to);
        $added = array_values(array_diff($toKeys, $fromKeys));
        $removed = array_values(array_diff($fromKeys, $toKeys));
        $changed = [];

        foreach (array_intersect($fromKeys, $toKeys) as $key) {
            if ($this->canonical($from[$key]) !== $this->canonical($to[$key])) {
                $changed[] = $key;
            }
        }

        sort($added);
        sort($removed);
        sort($changed);

        return compact('added', 'removed', 'changed');
    }

    /** @return array<string,mixed> */
    private function keyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (! array_is_list($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $index => $item) {
            if (is_array($item) && is_string($item['key'] ?? null)) {
                $result[$item['key']] = $item;
            } else {
                $result[(string) $index] = $item;
            }
        }

        return $result;
    }

    private function canonical(mixed $value): string
    {
        return json_encode($this->sortRecursive($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }
}
