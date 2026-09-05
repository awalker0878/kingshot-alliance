<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

interface GiftCodePushTransport
{
    public function provider(): string;

    public function supports(GiftCodeSourceRegistry $source): bool;
}
