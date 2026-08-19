<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\ActiveGovernorAuthorityContextResolver;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCurrentPlayerContextVersion
{
    public const HEADER_NAME = 'X-Game-Context-Version';

    public const ERROR_HEADER = 'X-Game-Context-Error';

    /** @var list<string> */
    private const EXEMPT_ROUTE_NAMES = [
        'invitations.accept',
        'logout',
        'players.activate',
    ];

    /** @var list<string> */
    private const EXEMPT_ROUTE_PREFIXES = [
        'account.',
        'login.',
        'password.',
        'platform.',
        'profile.',
        'public.',
        'register.',
        'two-factor.',
        'verification.',
    ];

    public function __construct(
        private PlayerContext $context,
        private ActiveGovernorAuthorityContextResolver $authorityContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSafeMethod($request) || $this->isPlatformMutation($request)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof AuthenticatedAccount) {
            return $next($request);
        }

        $selected = $this->context->playerOrNull();
        if ($selected === null) {
            return $this->stale('active_player_missing');
        }

        // Re-resolve ownership, membership, roles and Kingdom permissions from
        // persistence. The immutable snapshot is a staleness precondition only;
        // owning domains still authorize again inside their write transactions.
        $current = $this->authorityContext->resolveOwned((int) $user->id, $selected->playerId);
        if ($current === null) {
            return $this->stale('active_player_unavailable');
        }

        $providedVersion = $request->header(self::HEADER_NAME);
        if (! is_string($providedVersion) || ! hash_equals($current->authorityVersion, $providedVersion)) {
            return $this->stale('authority_context_changed');
        }

        // Observability/debugging only. This attribute is not authority.
        $request->attributes->set('authority_context_version', $current->authorityVersion);

        return $next($request);
    }

    private function isSafeMethod(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function isPlatformMutation(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return false;
        }

        if (in_array($routeName, self::EXEMPT_ROUTE_NAMES, true)) {
            return true;
        }

        foreach (self::EXEMPT_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function stale(string $reason): JsonResponse
    {
        return response()
            ->json([
                'code' => 'CONTEXT_STALE',
                'reason' => $reason,
                'message' => 'The active Governor or authority context changed. Reload the current context and try again.',
            ], 409)
            ->header(self::ERROR_HEADER, 'stale');
    }
}
