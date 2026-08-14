<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventResponseChoice: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case Unavailable = 'unavailable';
}
