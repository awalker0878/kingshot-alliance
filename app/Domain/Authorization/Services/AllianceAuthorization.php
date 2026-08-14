<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;

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

    public function allows(Player $player, Alliance $alliance, PermissionKey $permission): bool
    {
        $membership = $this->activeMembership($player, $alliance);

        return $membership instanceof AllianceMembership
            && $this->permissions->allows($membership, $alliance, $permission);
    }
}
