<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Enums;

enum AuthenticationType: string
{
    case Password = 'password';
    case Google = 'google';
}
