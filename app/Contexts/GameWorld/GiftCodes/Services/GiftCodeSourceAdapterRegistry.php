<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use InvalidArgumentException;

final class GiftCodeSourceAdapterRegistry
{
    /** @var array<string,GiftCodeSourceAdapter> */
    private array $adapters = [];

    /** @param iterable<GiftCodeSourceAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $key = trim($adapter->key());
            if ($key === '' || isset($this->adapters[$key])) {
                throw new InvalidArgumentException('Gift Code source adapter keys must be non-empty and unique.');
            }
            $this->adapters[$key] = $adapter;
        }
    }

    public function find(?string $key): ?GiftCodeSourceAdapter
    {
        return $key === null ? null : ($this->adapters[$key] ?? null);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->adapters);
    }
}
