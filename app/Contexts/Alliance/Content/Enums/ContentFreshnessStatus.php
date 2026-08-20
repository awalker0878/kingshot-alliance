<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum ContentFreshnessStatus: string
{
    case Current = 'current';
    case DueSoon = 'due_soon';
    case Stale = 'stale';
    case NotApplicable = 'not_applicable';
}
