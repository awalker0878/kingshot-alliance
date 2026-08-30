<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Middleware;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeCuratorGrant;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireGiftCodeCurator
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('game_world.gift_codes.moderation', false), 404);

        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        $account = $this->accounts->require((int) $identifier);
        abort_unless($account->emailVerified, 403, 'Gift Code curators must have a verified email address.');
        abort_unless($account->multiFactorConfirmed, 403, 'Gift Code curators must enable multi-factor authentication.');

        $authorized = $this->platformAuthorization->allows($account)
            || GiftCodeCuratorGrant::activeForUserId($account->userId);
        abort_unless($authorized, 403, 'Gift Code curator access is required.');

        $request->attributes->set('gift_code_curator_user_id', $account->userId);

        return $next($request);
    }
}
