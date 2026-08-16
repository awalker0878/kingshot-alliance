<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Services;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;

final class PlatformAdministratorAuthorization
{
    public function authorize(User $actor): void
    {
        if (! PlatformAdministrator::activeFor($actor)) {
            throw new AuthorizationException('Platform administrator access is required.');
        }
    }
}
