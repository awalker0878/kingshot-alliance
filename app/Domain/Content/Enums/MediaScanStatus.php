<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum MediaScanStatus: string
{
    case Clean = 'clean';
    case Rejected = 'rejected';
}
