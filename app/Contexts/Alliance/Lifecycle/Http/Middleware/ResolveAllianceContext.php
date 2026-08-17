<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Http\Middleware;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\TenantContextSnapshot;
use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveAllianceContext
{
    public function __construct(
        private AllianceContext $context,
        private PlayerContext $players,
        private ActiveAllianceScopeQuery $memberships,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $player = $this->players->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before opening Alliance operations.');

        $scope = $this->memberships->findForPlayer($player->playerId, $player->kingdomId);
        abort_unless($scope instanceof AllianceScopeReference, 409, 'The active Player is not currently in an active Alliance in this Kingdom.');

        $this->context->activate($player, $scope);
        $request->attributes->set('active_player_id', $scope->playerId);
        $request->attributes->set('alliance_id', $scope->allianceId);
        $request->attributes->set('tenant_context', TenantContextSnapshot::fromRequest($request));

        try {
            return $next($request);
        } finally {
            $request->attributes->remove('tenant_context');
            $this->context->clear();
        }
    }
}
