<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllianceAuthorization
{
    public function __construct(private AlliancePermissionEvaluator $permissions) {}

    public function activeMembership(Player $player, Alliance $alliance): ?AllianceMembership
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $player->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return null;
        }

        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
    }

    public function allows(Player $player, Alliance $alliance, AlliancePermission $permission): bool
    {
        $membership = $this->activeMembership($player, $alliance);

        return $membership instanceof AllianceMembership
            && $this->permissions->allows($membership, $alliance, $permission);
    }

    public function require(Player $actor, Alliance $alliance, AlliancePermission $permission): AllianceMutationContext
    {
        $context = $this->acquire($actor, $alliance, false);
        $this->assertAllowed($context, $permission);

        return $context;
    }

    public function requireExclusive(Player $actor, Alliance $alliance, AlliancePermission $permission): AllianceMutationContext
    {
        $context = $this->acquire($actor, $alliance, true);
        $this->assertAllowed($context, $permission);

        return $context;
    }

    public function acquireActiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, false);
    }

    public function acquireExclusiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, true);
    }

    private function acquire(Player $actor, Alliance $alliance, bool $exclusiveAlliance): AllianceMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance transactional authorization must run inside a database transaction.');
        }

        $allianceQuery = Alliance::query()->whereKey($alliance->id);
        $currentAlliance = $exclusiveAlliance
            ? $allianceQuery->lockForUpdate()->firstOrFail()
            : $allianceQuery->sharedLock()->firstOrFail();

        if ($currentAlliance->status !== AllianceStatus::Active) {
            throw new AuthorizationException;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $currentAlliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw new AuthorizationException;
        }

        $currentActor = Player::query()->whereKey($membership->player_id)->firstOrFail();
        if ((string) $currentActor->current_kingdom_id !== (string) $currentAlliance->kingdom_id) {
            throw new AuthorizationException;
        }

        return new AllianceMutationContext($currentAlliance, $currentActor, $membership);
    }

    private function assertAllowed(AllianceMutationContext $context, AlliancePermission $permission): void
    {
        if (! $this->permissions->allows($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }
    }
}
