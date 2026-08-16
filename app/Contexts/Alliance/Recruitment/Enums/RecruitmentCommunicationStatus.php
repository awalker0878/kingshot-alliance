<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Enums;

enum RecruitmentCommunicationStatus: string
{
    case Prepared = 'prepared';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
