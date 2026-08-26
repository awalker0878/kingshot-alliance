<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\ValueObjects;

final readonly class GovernorProgressionEvidenceRecordResult
{
    public function __construct(
        public string $receiptId,
        public string $observationId,
        public bool $idempotentReplay,
    ) {}

    /** @return array{receipt_id:string,observation_id:string,idempotent_replay:bool} */
    public function toArray(): array
    {
        return [
            'receipt_id' => $this->receiptId,
            'observation_id' => $this->observationId,
            'idempotent_replay' => $this->idempotentReplay,
        ];
    }
}
