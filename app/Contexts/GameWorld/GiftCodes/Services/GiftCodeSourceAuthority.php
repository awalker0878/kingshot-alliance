<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceAuthority
{
    public function canAutoVerify(string $classification, bool $verificationPassed): bool
    {
        return $verificationPassed && $classification === 'official';
    }
}
