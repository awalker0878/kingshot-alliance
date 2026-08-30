<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeIngestionSweep
{
    public function __construct(
        public int $sourceCount,
        public int $examined,
        public int $accepted,
        public int $duplicates,
        public int $quarantined,
        public int $failedSources,
        public ?string $nextSourceCursor,
        public int $durationMs,
    ) {}

    /** @return array<string,int|string|null> */
    public function toArray(): array
    {
        return [
            'sourceCount' => $this->sourceCount,
            'examined' => $this->examined,
            'accepted' => $this->accepted,
            'duplicates' => $this->duplicates,
            'quarantined' => $this->quarantined,
            'failedSources' => $this->failedSources,
            'nextSourceCursor' => $this->nextSourceCursor,
            'durationMs' => $this->durationMs,
        ];
    }
}
