<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceCommitStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
