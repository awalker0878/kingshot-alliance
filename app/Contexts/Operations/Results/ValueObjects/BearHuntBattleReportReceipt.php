<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\ValueObjects;

final readonly class BearHuntBattleReportReceipt
{
    /** @param list<array{playerId:string,score:int,rank:?int}> $playerResults */
    public function __construct(
        public string $reportId,
        public int $entryCount,
        public bool $idempotentReplay,
        public array $playerResults,
    ) {}

    /** @return array{reportId:string,entryCount:int,idempotentReplay:bool,playerResults:list<array{playerId:string,score:int,rank:?int}>} */
    public function toArray(): array
    {
        return [
            'reportId' => $this->reportId,
            'entryCount' => $this->entryCount,
            'idempotentReplay' => $this->idempotentReplay,
            'playerResults' => $this->playerResults,
        ];
    }
}
