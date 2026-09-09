<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class TransferObservationSelectorBooleanV3Test extends TestCase
{
    public function test_resource_protection_boolean_observation_returns_boolean_value(): void
    {
        $now = CarbonImmutable::parse('2026-09-07T18:00:00Z');
        $observation = new TransferObservation([
            'kind' => TransferObservationKind::ResourceProtectionVerified,
            'boolean_value' => true,
            'source_type' => TransferSourceType::InGame,
            'source_reference' => 'KingShot transfer pre-flight screen',
            'observed_at' => $now->subMinute(),
            'valid_until' => $now->addHour(),
        ]);

        $selected = app(TransferObservationSelector::class)->select(
            collect([$observation]),
            TransferObservationKind::ResourceProtectionVerified,
            null,
            $now,
        );

        self::assertSame(TransferRequirementState::Met, $selected->state);
        self::assertTrue($selected->value);
    }
}
