<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventPollType: string
{
    case Choice = 'choice';
    case TimeVote = 'time_vote';
}
