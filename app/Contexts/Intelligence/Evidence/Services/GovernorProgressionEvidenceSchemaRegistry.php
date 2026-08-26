<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\GovernorProgressionEvidenceSchema;
use InvalidArgumentException;

final class GovernorProgressionEvidenceSchemaRegistry
{
    public function require(EvidenceKind $kind): GovernorProgressionEvidenceSchema
    {
        return match ($kind) {
            EvidenceKind::GovernorProfile => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-profile/1',
                supportedFields: ['observed_name', 'power', 'progression_level', 'observed_alliance_tag', 'kingdom_number'],
                requiredFields: [],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-profile-v1',
                destinationAction: 'RecordGovernorProfileEvidence',
            ),
            EvidenceKind::GovernorHeroRoster => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-hero-roster/1',
                supportedFields: ['hero_name', 'level', 'star', 'widget_level'],
                requiredFields: ['hero_name'],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-hero-roster-v1',
                destinationAction: 'RecordHeroRosterEvidence',
            ),
            EvidenceKind::GovernorHeroDetail => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-hero-detail/1',
                supportedFields: ['hero_name', 'level', 'star', 'substar', 'widget_level'],
                requiredFields: ['hero_name'],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-hero-detail-v1',
                destinationAction: 'RecordHeroDetailEvidence',
            ),
            EvidenceKind::GovernorHeroGear => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-hero-gear/1',
                supportedFields: ['hero_name', 'gear_slot', 'gear_quality', 'gear_level', 'mastery_level'],
                requiredFields: ['hero_name', 'gear_slot'],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-hero-gear-v1',
                destinationAction: 'RecordHeroGearEvidence',
            ),
            EvidenceKind::GovernorGear => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-gear/1',
                supportedFields: ['gear_slot', 'gear_quality', 'gear_level', 'gear_star'],
                requiredFields: ['gear_slot'],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-gear-v1',
                destinationAction: 'RecordGovernorGearEvidence',
            ),
            EvidenceKind::GovernorCharms => new GovernorProgressionEvidenceSchema(
                kind: $kind,
                version: 'governor-charms/1',
                supportedFields: ['charm_slot', 'charm_name', 'charm_level'],
                requiredFields: ['charm_slot'],
                minimumClassificationConfidence: 0.60,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'governor-charms-v1',
                destinationAction: 'RecordGovernorCharmsEvidence',
            ),
            default => throw new InvalidArgumentException('Evidence kind is not a Governor Progression screenshot schema.'),
        };
    }
}
