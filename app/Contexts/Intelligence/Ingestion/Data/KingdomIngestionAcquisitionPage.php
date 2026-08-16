<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Data;

use InvalidArgumentException;

final readonly class KingdomIngestionAcquisitionPage
{
    public const MAX_RECORDS = 250;

    public string $sourceWindowId;

    public ?string $nextCursor;

    /** @var list<array<string, mixed>> */
    public array $records;

    /** @param list<array<string, mixed>> $records */
    public function __construct(string $sourceWindowId, ?string $nextCursor, array $records)
    {
        $sourceWindowId = trim($sourceWindowId);
        if ($sourceWindowId === '' || mb_strlen($sourceWindowId) > 191) {
            throw new InvalidArgumentException('Kingdom ingestion source-window identifiers must contain 1-191 characters.');
        }

        if ($nextCursor !== null) {
            $nextCursor = trim($nextCursor);
            $nextCursor = $nextCursor === '' ? null : $nextCursor;
        }

        if ($nextCursor !== null && mb_strlen($nextCursor) > 255) {
            throw new InvalidArgumentException('Kingdom ingestion source cursors cannot exceed 255 characters.');
        }

        if (count($records) > self::MAX_RECORDS) {
            throw new InvalidArgumentException('Kingdom ingestion acquisition pages cannot exceed 250 records.');
        }

        $this->sourceWindowId = $sourceWindowId;
        $this->nextCursor = $nextCursor;
        $this->records = $records;
    }
}
