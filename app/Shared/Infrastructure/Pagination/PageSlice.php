<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Pagination;

use InvalidArgumentException;

/** @template T */
final readonly class PageSlice
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public int $pageSize,
        public bool $isFirstPage = true,
    ) {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Page size must be positive.');
        }
    }

    /** @return array{items: list<T>, nextCursor: string|null, hasMore: bool, pageSize: int, isFirstPage: bool} */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'nextCursor' => $this->nextCursor,
            'hasMore' => $this->nextCursor !== null,
            'pageSize' => $this->pageSize,
            'isFirstPage' => $this->isFirstPage,
        ];
    }
}
