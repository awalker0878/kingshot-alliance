<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum ContentVisibility: string
{
    case Public = 'public';
    case Members = 'members';
}
