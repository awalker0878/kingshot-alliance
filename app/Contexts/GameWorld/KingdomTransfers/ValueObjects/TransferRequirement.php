<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementKey;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Carbon\CarbonImmutable;

final readonly class TransferRequirement
{
    public function __construct(
        public TransferRequirementKey $key,
        public TransferRequirementState $state,
        public string $explanation,
        public int|string|bool|null $actual = null,
        public int|string|bool|null $required = null,
        public ?string $nextAction = null,
        public ?TransferSourceType $sourceType = null,
        public ?string $sourceReference = null,
        public ?CarbonImmutable $observedAt = null,
        public ?CarbonImmutable $validUntil = null,
    ) {}
}
