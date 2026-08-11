<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Contracts;

use App\Domain\Kingdoms\Data\KingdomIngestionAcquisitionPage;

interface KingdomIngestionAcquisitionAdapter extends KingdomIngestionAdapter
{
    public function pollIntervalSeconds(): int;

    public function acquire(?string $cursor, int $limit): KingdomIngestionAcquisitionPage;
}
