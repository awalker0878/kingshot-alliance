<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceActivationModes
{
    /** @return list<string> */
    public static function supported(): array
    {
        return ['pull', 'push', 'hybrid', 'manual'];
    }
}
