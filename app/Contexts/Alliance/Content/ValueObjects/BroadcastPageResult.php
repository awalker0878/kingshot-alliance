<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\ValueObjects;

final readonly class BroadcastPageResult
{
    public function __construct(public int $examined, public bool $completed) {}
}
