<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final readonly class BulkActionResult
{
    /** @param non-empty-list<BulkItemResult> $items */
    public function __construct(
        public string $action,
        public array $items,
    ) {}

    /**
     * @return array{
     *   action: string,
     *   items: non-empty-list<array{itemId: string, label: string, outcome: string, code: string}>,
     *   succeeded: int,
     *   failed: int,
     *   skipped: int,
     *   failedItemIds: list<string>
     * }
     */
    public function toArray(): array
    {
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $failedItemIds = [];

        foreach ($this->items as $item) {
            if ($item->outcome === 'succeeded') {
                $succeeded++;
            } elseif ($item->outcome === 'failed') {
                $failed++;
                $failedItemIds[] = $item->itemId;
            } else {
                $skipped++;
            }
        }

        return [
            'action' => $this->action,
            'items' => array_map(
                static fn (BulkItemResult $item): array => $item->toArray(),
                $this->items,
            ),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'failedItemIds' => $failedItemIds,
        ];
    }
}
