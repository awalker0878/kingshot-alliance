<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeProviderHighWater
{
    public function greaterNumericId(?string $current, string $candidate): string
    {
        if ($current === null || strlen($candidate) > strlen($current)) {
            return $candidate;
        }
        if (strlen($candidate) < strlen($current)) {
            return $current;
        }

        return strcmp($candidate, $current) > 0 ? $candidate : $current;
    }
}
