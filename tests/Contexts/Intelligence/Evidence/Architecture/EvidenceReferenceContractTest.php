<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Evidence\Architecture;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EvidenceReferenceContractTest extends TestCase
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
}
