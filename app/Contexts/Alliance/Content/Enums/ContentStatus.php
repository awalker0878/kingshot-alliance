<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
