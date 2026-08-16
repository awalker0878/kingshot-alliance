<?php

declare(strict_types=1);

namespace App\Shared\Access\Contracts;

interface Permission
{
    public function key(): string;
}
