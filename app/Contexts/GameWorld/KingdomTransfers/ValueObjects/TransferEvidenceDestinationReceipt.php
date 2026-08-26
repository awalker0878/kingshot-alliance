<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

final readonly class TransferEvidenceDestinationReceipt
{
    /** @param array<string,string> $destinationIds */
    public function __construct(
        public string $receiptId,
        public array $destinationIds,
        public bool $idempotentReplay,
    ) {}
}
