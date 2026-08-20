<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeSubmissionResult
{
    public function __construct(
        public GiftCodeReference $giftCode,
        public bool $duplicateDetected,
        public bool $provenanceAdded,
    ) {}
}
