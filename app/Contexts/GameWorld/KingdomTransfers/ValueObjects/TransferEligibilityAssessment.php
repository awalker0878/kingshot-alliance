<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use Carbon\CarbonImmutable;

final readonly class TransferEligibilityAssessment
{
    /** @param list<TransferRequirement> $requirements */
    public function __construct(
        public TransferEligibilityOutcome $outcome,
        public array $requirements,
        public ?string $primaryAction,
        public CarbonImmutable $evaluatedAt,
    ) {}
}
