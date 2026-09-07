<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\ValueObjects;

final readonly class Rectangle
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    public function right(): int
    {
        return $this->x + $this->width;
    }

    public function bottom(): int
    {
        return $this->y + $this->height;
    }

    public function area(): int
    {
        return max(0, $this->width) * max(0, $this->height);
    }

    public function intersects(self $other): bool
    {
        return $this->x < $other->right()
            && $this->right() > $other->x
            && $this->y < $other->bottom()
            && $this->bottom() > $other->y;
    }

    public function touchesOrIntersects(self $other): bool
    {
        return $this->x <= $other->right()
            && $this->right() >= $other->x
            && $this->y <= $other->bottom()
            && $this->bottom() >= $other->y;
    }

    public function inside(self $bounds): bool
    {
        return $this->x >= $bounds->x
            && $this->y >= $bounds->y
            && $this->right() <= $bounds->right()
            && $this->bottom() <= $bounds->bottom();
    }

    public function containsCell(int $x, int $y): bool
    {
        return $x >= $this->x
            && $x + 1 <= $this->right()
            && $y >= $this->y
            && $y + 1 <= $this->bottom();
    }
}
