<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeFactDecision
{
    /**
     * @param  array<string, mixed>|null  $value
     * @param  list<string>  $evidenceIds
     */
    public function __construct(
        public string $factType,
        public bool $qualified,
        public string $reasonCode,
        public ?array $value,
        public array $evidenceIds,
    ) {}
}
