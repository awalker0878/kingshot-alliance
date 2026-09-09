<?php

declare(strict_types=1);

namespace Tests\Architecture\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Queries\EvidenceReferenceQuery;
use Tests\TestCase;

final class GovernorProgressionEvidenceBindingTest extends TestCase
{
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
