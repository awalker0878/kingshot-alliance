<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Enums;

enum GoogleAuthenticationIntent: string
{
    case Login = 'login';
    case Register = 'register';
    case Reauthenticate = 'reauthenticate';
    case Connect = 'connect';
}
