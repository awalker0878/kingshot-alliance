<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use RuntimeException;

final class KingdomMapFactProvenanceValidator
{
    /** @param array<string,mixed> $data */
    public function validate(array $data): void
    {
        $sources = $data['sources'] ?? null;
        if (! is_array($sources) || array_is_list($sources)) {
            throw new RuntimeException('Kingdom map fact provenance requires a source registry.');
        }

        $this->walk($data, $sources, 'dataset');
    }

    /** @param array<string,mixed> $sources */
    private function walk(mixed $value, array $sources, string $path): void
    {
        if (! is_array($value)) {
            return;
        }

        if (isset($value['facts'])) {
            $facts = $value['facts'];
            if (! is_array($facts) || array_is_list($facts)) {
                $this->fail($path.'.facts must be an object.');
            }
            foreach ($facts as $factKey => $fact) {
                if (! is_string($factKey) || ! is_array($fact) || array_is_list($fact)) {
                    $this->fail($path.'.facts entries must be keyed objects.');
                }
                $this->validateFact($fact, $sources, $path.'.facts.'.$factKey);
            }
        }

        foreach ($value as $key => $child) {
            if ($key === 'sources') {
                continue;
            }
            $this->walk($child, $sources, $path.'.'.(string) $key);
        }
    }

    /**
     * @param  array<string,mixed>  $fact
     * @param  array<string,mixed>  $sources
     */
    private function validateFact(array $fact, array $sources, string $path): void
    {
        $confidenceValue = $fact['confidence'] ?? null;
        $confidence = is_string($confidenceValue) ? MapDatasetConfidence::tryFrom($confidenceValue) : null;
        if (! $confidence instanceof MapDatasetConfidence) {
            $this->fail($path.'.confidence is unsupported.');
        }

        $provenance = $fact['provenance'] ?? null;
        if (! is_array($provenance) || ! array_is_list($provenance) || $provenance === []) {
            $this->fail($path.'.provenance must contain at least one source ID.');
        }

        foreach ($provenance as $sourceId) {
            if (! is_string($sourceId) || ! is_array($sources[$sourceId] ?? null)) {
                $this->fail($path.'.provenance references an unknown source.');
            }
            $ceilingValue = $sources[$sourceId]['confidence_ceiling'] ?? null;
            $ceiling = is_string($ceilingValue) ? MapDatasetConfidence::tryFrom($ceilingValue) : null;
            if (! $ceiling instanceof MapDatasetConfidence) {
                $this->fail('source '.$sourceId.' has an invalid confidence ceiling.');
            }
            if ($this->rank($confidence) > $this->rank($ceiling)) {
                $this->fail($path.'.confidence exceeds source '.$sourceId.' confidence ceiling.');
            }
        }
    }

    private function rank(MapDatasetConfidence $confidence): int
    {
        return match ($confidence) {
            MapDatasetConfidence::Unknown => 0,
            MapDatasetConfidence::Disputed => 1,
            MapDatasetConfidence::CommunityObserved => 2,
            MapDatasetConfidence::VerifiedObservation => 3,
            MapDatasetConfidence::Official => 4,
        };
    }

    private function fail(string $message): never
    {
        throw new RuntimeException('Kingdom map fact provenance validation failed: '.$message);
    }
}
