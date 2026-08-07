<?php

declare(strict_types=1);

namespace App\Domain\Events;

use InvalidArgumentException;

final readonly class FormationComposition
{
    public function __construct(
        public int $infantryPercent,
        public int $cavalryPercent,
        public int $archerPercent,
    ) {
        foreach ([$infantryPercent, $cavalryPercent, $archerPercent] as $percentage) {
            if ($percentage < 0 || $percentage > 100) {
                throw new InvalidArgumentException('Formation percentages must be between 0 and 100.');
            }
        }

        if (($infantryPercent + $cavalryPercent + $archerPercent) !== 100) {
            throw new InvalidArgumentException('Formation percentages must total 100.');
        }
    }

    /** @return array{infantry_percent:int,cavalry_percent:int,archer_percent:int} */
    public function toArray(): array
    {
        return [
            'infantry_percent' => $this->infantryPercent,
            'cavalry_percent' => $this->cavalryPercent,
            'archer_percent' => $this->archerPercent,
        ];
    }
}
