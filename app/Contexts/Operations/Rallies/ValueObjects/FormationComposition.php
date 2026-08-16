<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\ValueObjects;

use Illuminate\Validation\ValidationException;

final readonly class FormationComposition
{
    public function __construct(
        public int $infantry,
        public int $cavalry,
        public int $archer,
    ) {
        foreach (['infantry' => $infantry, 'cavalry' => $cavalry, 'archer' => $archer] as $key => $value) {
            if ($value < 0 || $value > 100) {
                throw ValidationException::withMessages([$key.'_percent' => 'Troop percentages must be between 0 and 100.']);
            }
        }
        if ($infantry + $cavalry + $archer !== 100) {
            throw ValidationException::withMessages(['formation' => 'Infantry, cavalry, and archer percentages must total exactly 100.']);
        }
    }

    /** @return array{infantry_percent:int,cavalry_percent:int,archer_percent:int} */
    public function toArray(): array
    {
        return [
            'infantry_percent' => $this->infantry,
            'cavalry_percent' => $this->cavalry,
            'archer_percent' => $this->archer,
        ];
    }
}
