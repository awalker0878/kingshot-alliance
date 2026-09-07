<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;

final class KingdomMapSourceVerifier
{
    /**
     * @return array{
     *   valid:bool,
     *   source_count:int,
     *   rights_complete:bool,
     *   ksmapper_authorized:bool,
     *   lineages:array<string,list<string>>,
     *   errors:list<string>
     * }
     */
    public function verify(KingdomMapDataset $dataset): array
    {
        $sources = $dataset->sources();
        $errors = [];
        $lineages = [];
        $rightsComplete = true;

        foreach ($sources as $sourceId => $source) {
            if (! is_string($sourceId) || ! is_array($source)) {
                $errors[] = 'Source registry contains an invalid entry.';

                continue;
            }
            $rights = $source['rights_basis'] ?? null;
            if (! is_string($rights) || trim($rights) === '') {
                $rightsComplete = false;
                $errors[] = 'Source '.$sourceId.' has no reuse-rights basis.';
            }
            $lineage = $source['lineage'] ?? null;
            if (! is_string($lineage) || trim($lineage) === '') {
                $errors[] = 'Source '.$sourceId.' has no lineage identifier.';
            } else {
                $lineages[$lineage][] = $sourceId;
            }

            $type = $source['type'] ?? null;
            $ceilingValue = $source['confidence_ceiling'] ?? null;
            $ceiling = is_string($ceilingValue) ? MapDatasetConfidence::tryFrom($ceilingValue) : null;
            if (! $ceiling instanceof MapDatasetConfidence) {
                $errors[] = 'Source '.$sourceId.' has an unsupported confidence ceiling.';

                continue;
            }
            if ($type !== 'official' && $ceiling === MapDatasetConfidence::Official) {
                $errors[] = 'Non-official source '.$sourceId.' cannot grant official confidence.';
            }
        }

        $ksmapper = $sources['ksmapper'] ?? null;
        $ksmapperAuthorized = is_array($ksmapper)
            && ($ksmapper['rights_basis'] ?? null) === 'user_authorized_reuse_2026-09-06'
            && ($ksmapper['confidence_ceiling'] ?? null) === MapDatasetConfidence::CommunityObserved->value;
        if (! $ksmapperAuthorized) {
            $errors[] = 'ksmapper must retain the recorded user-authorized reuse basis and community-observed confidence ceiling.';
        }

        ksort($lineages);
        foreach ($lineages as &$sourceIds) {
            sort($sourceIds);
        }
        unset($sourceIds);

        return [
            'valid' => $errors === [],
            'source_count' => count($sources),
            'rights_complete' => $rightsComplete,
            'ksmapper_authorized' => $ksmapperAuthorized,
            'lineages' => $lineages,
            'errors' => $errors,
        ];
    }

    /** @param list<string> $sourceIds */
    public function independentLineageCount(KingdomMapDataset $dataset, array $sourceIds): int
    {
        $lineages = [];
        $sources = $dataset->sources();
        foreach ($sourceIds as $sourceId) {
            $source = $sources[$sourceId] ?? null;
            if (! is_array($source) || ! is_string($source['lineage'] ?? null)) {
                continue;
            }
            $lineages[$source['lineage']] = true;
        }

        return count($lineages);
    }
}
