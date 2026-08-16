<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Contracts;

use App\Contexts\Intelligence\Ingestion\Data\KingdomIngestionAcquisitionPage;

interface KingdomIngestionAcquisitionAdapter extends KingdomIngestionAdapter
{
    public function pollIntervalSeconds(): int;

    public function acquire(?string $cursor, int $limit): KingdomIngestionAcquisitionPage;
}
