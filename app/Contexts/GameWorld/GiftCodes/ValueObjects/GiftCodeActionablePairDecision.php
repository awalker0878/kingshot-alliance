<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class GiftCodeActionablePairDecision
{
    public function __construct(
        public bool $actionable,
        public string $reason,
        public ?CarbonImmutable $retryAt = null,
    ) {}

    public static function actionable(): self
    {
        return new self(true, 'actionable');
    }

    public static function unavailable(string $reason, ?CarbonImmutable $retryAt = null): self
    {
        return new self(false, $reason, $retryAt);
    }
}
