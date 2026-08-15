<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Middleware;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Alliances\ValueObjects\TenantContextSnapshot;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveAllianceContext
{
    public function __construct(
        private AllianceContext $context,
        private PlayerContext $players,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $player = $this->players->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before opening Alliance operations.');

        $membership = AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('alliance', static fn ($query) => $query->where('kingdom_id', $player->current_kingdom_id))
            ->with('alliance')
            ->first();

        abort_unless($membership instanceof AllianceMembership, 409, 'The active Player is not currently in an Alliance.');

        $alliance = $membership->alliance;
        if (! $alliance instanceof Alliance) {
            throw new LogicException('An active membership must reference an Alliance.');
        }

        abort_unless($alliance->status === AllianceStatus::Active, 403, 'The Alliance is not currently active.');

        $this->context->activate($player, $membership, $alliance);
        $request->attributes->set('active_player_id', (string) $player->id);
        $request->attributes->set('alliance_id', (string) $alliance->id);
        $request->attributes->set('tenant_context', TenantContextSnapshot::fromRequest($request));

        try {
            return $next($request);
        } finally {
            $request->attributes->remove('tenant_context');
            $this->context->clear();
        }
    }
}
