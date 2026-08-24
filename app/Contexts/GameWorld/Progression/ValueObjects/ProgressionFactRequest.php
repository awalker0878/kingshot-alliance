<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;

final readonly class ProgressionFactRequest
{
    public function __construct(
        public ProgressionFactKind $kind,
        public string $subject,
        public ?int $level = null,
    ) {}
}
