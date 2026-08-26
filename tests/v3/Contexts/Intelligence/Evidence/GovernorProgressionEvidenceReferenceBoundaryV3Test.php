<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Queries\EvidenceReferenceQuery;
use ReflectionClass;
use Tests\v3\TestCase;

final class GovernorProgressionEvidenceReferenceBoundaryV3Test extends TestCase
{
    public function test_general_evidence_reference_contract_remains_family_neutral(): void
    {
        $reflection = new ReflectionClass(EvidenceReferenceLookup::class);
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($methods);

        self::assertSame(['belongsToAlliance', 'isApprovedForAlliance'], $methods);
    }

    public function test_governor_progression_provenance_uses_dedicated_evidence_owner_contract(): void
    {
        self::assertInstanceOf(
            GovernorProgressionEvidenceReferenceLookup::class,
            app(GovernorProgressionEvidenceReferenceLookup::class),
        );
        self::assertInstanceOf(
            GovernorProgressionEvidenceReferenceLookup::class,
            app(EvidenceReferenceQuery::class),
        );
    }
}
