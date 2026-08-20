<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class ExternalActionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public bool $replayed,
    ) {}
}
