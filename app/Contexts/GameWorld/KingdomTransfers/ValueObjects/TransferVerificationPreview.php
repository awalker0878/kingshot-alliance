<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

/** Recomputed owner evidence with explicit coverage; not persisted eligibility truth. */
final readonly class TransferVerificationPreview
{
    /** @param list<string> $affectedIds Identities from the assessed preview only. */
    public function __construct(
        public string $planId,
        public ?string $updatedAt,
        public bool $windowAvailable,
        public int $total,
        public int $assessed,
        public int $manualBlocked,
        public int $knownAffected,
        public array $affectedIds,
    ) {}

    /** @return array{total:int,assessed:int,unassessed:int,manualBlocked:int,complete:bool,affectedIdsComplete:bool} */
    public function coverage(): array
    {
        return ['total' => $this->total, 'assessed' => $this->assessed,
            'unassessed' => max(0, $this->total - $this->assessed), 'manualBlocked' => $this->manualBlocked,
            'complete' => $this->windowAvailable && $this->assessed === $this->total,
            'affectedIdsComplete' => $this->windowAvailable && $this->assessed === $this->total && count($this->affectedIds) === $this->knownAffected];
    }
}
