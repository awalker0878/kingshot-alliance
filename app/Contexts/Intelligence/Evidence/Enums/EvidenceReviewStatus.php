<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceReviewStatus: string
{
    case Approved = 'approved';
    case DuplicateBlocked = 'duplicate_blocked';
}
