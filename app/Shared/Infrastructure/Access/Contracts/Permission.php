<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Access\Contracts;

interface Permission
{
    public function key(): string;
}
