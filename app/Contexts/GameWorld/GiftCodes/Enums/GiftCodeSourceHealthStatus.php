<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSourceHealthStatus: string
{
    case Disabled = 'disabled';
    case Idle = 'idle';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case RateLimited = 'rate_limited';
    case AuthenticationFailed = 'authentication_failed';
    case PermissionRevoked = 'permission_revoked';
    case ContractChanged = 'contract_changed';
    case ParserFailed = 'parser_failed';
    case Failing = 'failing';
}
