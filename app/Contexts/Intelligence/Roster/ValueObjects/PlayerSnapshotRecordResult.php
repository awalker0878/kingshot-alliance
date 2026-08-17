<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\ValueObjects;

final readonly class PlayerSnapshotRecordResult
{
    public function __construct(
        public string $snapshotId,
        public bool $created,
    ) {}
}
