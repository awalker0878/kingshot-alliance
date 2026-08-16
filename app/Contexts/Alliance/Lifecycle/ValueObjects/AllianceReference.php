<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\ValueObjects;

final readonly class AllianceReference
{
    public function __construct(
        public string $allianceId,
        public string $name,
        public string $slug,
        public string $kingdomId,
        public string $language,
        public string $timezone,
        public string $status,
    ) {}

    public function active(): bool
    {
        return $this->status === 'active';
    }
}
