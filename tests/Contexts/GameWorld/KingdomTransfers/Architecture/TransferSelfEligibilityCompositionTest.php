<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Architecture;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferSelfEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferKingdomConditionSelector;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Tests\Support\RepositoryPath;

final class TransferSelfEligibilityCompositionTest extends TestCase
{
    public function test_self_projection_delegates_current_facts_to_the_canonical_eligibility_query(): void
    {
        $constructor = (new ReflectionClass(TransferSelfEligibilityQuery::class))->getConstructor();
        self::assertNotNull($constructor);
        $dependencies = array_map(static function ($parameter): string {
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);

            return $type->getName();
        }, $constructor->getParameters());
        self::assertContains(TransferEligibilityQuery::class, $dependencies);
        foreach ([TransferEligibilityEvaluator::class, TransferObservationSelector::class, TransferKingdomConditionSelector::class] as $dependency) {
            self::assertNotContains($dependency, $dependencies, 'Self projection must not reconstruct the authoritative evaluation.');
        }
        $source = file_get_contents(RepositoryPath::fromRoot('app/Contexts/GameWorld/KingdomTransfers/Queries/TransferSelfEligibilityQuery.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString('new TransferEligibilityInput', $source);
        self::assertStringNotContainsString('new TransferEligibilityAssessment', $source);
    }
}
