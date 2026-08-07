<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ContentVisibility: string
{
    case Public = 'public';
    case Members = 'members';
}
