<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Access\ValueObjects;

use App\Shared\Infrastructure\Access\Contracts\Permission;

final readonly class NamedPermission implements Permission
{
    private function __construct(private string $key) {}

    public static function from(string $key): self
    {
        return new self($key);
    }

    public function key(): string
    {
        return $this->key;
    }
}
