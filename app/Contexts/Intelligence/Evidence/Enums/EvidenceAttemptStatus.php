<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceAttemptStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
