<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Enums;

enum ExternalActorProvider: string
{
    case Discord = 'discord';
    case Telegram = 'telegram';
}
