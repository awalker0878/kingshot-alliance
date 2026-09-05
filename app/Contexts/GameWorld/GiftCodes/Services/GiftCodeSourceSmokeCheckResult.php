<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceSmokeCheckResult
{
    /** @param list<string> $checks */
    public function __construct(
        public bool $passed,
        public array $checks,
        public ?string $failureCode = null,
    ) {}
}
