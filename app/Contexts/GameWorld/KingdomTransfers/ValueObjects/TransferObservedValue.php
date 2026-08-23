<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Carbon\CarbonImmutable;

final readonly class TransferObservedValue
{
    public function __construct(
        public TransferRequirementState $state,
        public int|string|bool|null $value = null,
        public ?TransferSourceType $sourceType = null,
        public ?string $sourceReference = null,
        public ?CarbonImmutable $observedAt = null,
        public ?CarbonImmutable $validUntil = null,
        public ?string $details = null,
    ) {}

    public static function unknown(): self
    {
        return new self(TransferRequirementState::Unknown);
    }
}
