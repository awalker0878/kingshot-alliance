<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\ValueObjects;

final readonly class AllianceRosterObservationBatchReceipt
{
    public function __construct(public string $batchId, public int $rowCount) {}

    /** @return array{batch_id:string,row_count:int} */
    public function toArray(): array
    {
        return ['batch_id' => $this->batchId, 'row_count' => $this->rowCount];
    }
}
