<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Services;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;

final class PlatformAdministratorAuthorization
{
    public function authorize(AccountIdentity $actor): void
    {
        if (! PlatformAdministrator::activeForUserId($actor->userId)) {
            throw new AuthorizationException('Platform administrator access is required.');
        }
    }
}
