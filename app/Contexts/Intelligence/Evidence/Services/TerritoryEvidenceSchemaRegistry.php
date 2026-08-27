<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\TerritoryEvidenceSchema;
use InvalidArgumentException;

final class TerritoryEvidenceSchemaRegistry
{
    public function require(EvidenceKind $kind): TerritoryEvidenceSchema
    {
        if ($kind !== EvidenceKind::TerritoryMapObservation) {
            throw new InvalidArgumentException('Evidence kind is not a Territory spatial screenshot schema.');
        }

        return new TerritoryEvidenceSchema(
            kind: $kind,
            version: 'territory-map-observation/1',
            supportedFields: ['headquarters_coordinate', 'bear_trap_coordinate', 'banner_coordinate', 'governor_city_coordinate', 'observed_label', 'visible_region_bounds', 'source_timestamp'],
            minimumClassificationConfidence: 0.60,
            minimumFieldConfidence: 0.55,
            fixtureCorpus: 'territory-map-observation-v1',
            destinationAction: 'RecordSpatialObservationEvidence',
        );
    }
}
