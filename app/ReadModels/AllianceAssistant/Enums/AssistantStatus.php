<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum AssistantStatus: string
{
    case Answered = 'answered';
    case Ambiguous = 'ambiguous';
    case NotFound = 'not_found';
    case Unsupported = 'unsupported';
    case ValidationError = 'validation_error';
    case Unavailable = 'unavailable';
}
