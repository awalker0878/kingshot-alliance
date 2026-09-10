<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Evidence\Architecture;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Queries\EvidenceReferenceQuery;
use Tests\TestCase;

final class EvidenceReferenceBindingTest extends TestCase
{
    public function test_evidence_owner_lookup_is_bound_through_its_contract(): void
    {
        self::assertInstanceOf(EvidenceReferenceLookup::class, app(EvidenceReferenceLookup::class));
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
