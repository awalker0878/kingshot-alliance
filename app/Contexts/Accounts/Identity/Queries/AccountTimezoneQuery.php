<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Queries;

use App\Contexts\Accounts\Identity\Models\User;
use DateTimeZone;
use Throwable;

final readonly class AccountTimezoneQuery
{
    public function forUser(int $userId): string
    {
        $timezone = User::query()->whereKey($userId)->value('timezone');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Throwable) {
            return 'UTC';
        }
    }
}
