<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;
use App\Contexts\GameWorld\Progression\Enums\ProgressionFactResolution;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionFactQuery;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionDataset;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactRequest;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactResult;
use ReflectionMethod;
use Tests\v3\TestCase;

final class ProgressionFactConflictV3Test extends TestCase
{
    public function test_unresolved_supported_family_conflict_remains_conflicting_without_inventing_a_value(): void
    {
        $base = app(ProgressionDatasetQuery::class)->latest();
        $release = $base->release;
        $conflicts = is_array($release['conflicts'] ?? null) ? $release['conflicts'] : [];
        $conflicts[] = [
            'id' => 'test-unresolved-hero-conflict',
            'family' => 'heroes',
            'description' => 'Synthetic unresolved conflict fixture.',
            'claims' => [],
            'resolution_status' => 'unresolved',
        ];
        $release['conflicts'] = $conflicts;

        $dataset = new ProgressionDataset(
            id: $base->id,
            schemaVersion: $base->schemaVersion,
            datasetVersion: $base->datasetVersion,
            observedAt: $base->observedAt,
            checksum: $base->checksum,
            release: $release,
            heroes: $base->heroes,
            systems: $base->systems,
            formations: $base->formations,
            catalogues: $base->catalogues,
        );

        $query = app(ProgressionFactQuery::class);
        $method = new ReflectionMethod($query, 'heroFact');
        $result = $method->invoke(
            $query,
            $dataset,
            new ProgressionFactRequest(ProgressionFactKind::HeroGeneration, 'Amadeus'),
            'generation',
            'Generation',
        );

        self::assertInstanceOf(ProgressionFactResult::class, $result);
        self::assertSame(ProgressionFactResolution::Conflicting, $result->resolution);
        self::assertSame([], $result->values);
        self::assertSame('conflicting', $result->evidenceStatus);
        self::assertSame($base->id, $result->datasetId);
        self::assertSame($base->checksum, $result->checksum);
    }
}
