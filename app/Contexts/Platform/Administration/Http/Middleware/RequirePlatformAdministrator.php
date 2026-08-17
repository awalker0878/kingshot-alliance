<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Http\Middleware;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePlatformAdministrator
{
    public function __construct(private AccountIdentityQuery $accounts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();
        $identifier = $principal?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        $account = $this->accounts->require((int) $identifier);
        abort_unless(
            $account->emailVerified,
            403,
            'Platform administrators must have a verified email address.',
        );
        abort_unless(
            $account->multiFactorConfirmed,
            403,
            'Platform administrators must enable multi-factor authentication.',
        );
        abort_unless(
            PlatformAdministrator::activeForUserId($account->userId),
            403,
            'Platform administrator access is required.',
        );

        $request->attributes->set('platform_administrator_user_id', $account->userId);

        return $next($request);
    }
}
