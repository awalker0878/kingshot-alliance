<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\ValueObjects;

final readonly class Coordinate
{
    public function __construct(public int $x, public int $y) {}

    /** @return array{x:int,y:int} */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public function distanceTo(self $other): float
    {
        return hypot($other->x - $this->x, $other->y - $this->y);
    }
}
