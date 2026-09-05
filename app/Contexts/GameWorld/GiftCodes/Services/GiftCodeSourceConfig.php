<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceConfig
{
    public function boolean(array $policy, string $key, bool $default = false): bool
    {
        $value = $policy[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }
}
