<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

final readonly class ReviewedBearHuntCommitCommand
{
    /** @param list<array{player_id:string,reported_rank:?int,damage_points:int}> $entries */
    public function __construct(
        public string $commitAttemptId,
        public string $evidenceId,
        public string $reviewId,
        public string $occurrenceId,
        public string $idempotencyKey,
        public string $reportFingerprint,
        public ?string $reportTimestampText,
        public array $entries,
    ) {}
}
