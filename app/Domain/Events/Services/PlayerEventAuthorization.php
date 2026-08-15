<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;

final class PlayerEventAuthorization
{
    public function __construct(private AllianceAuthorization $allianceAuthorization) {}

    public function allows(Player $actor, Player $target, PermissionKey $permission): bool
    {
        if (! in_array($permission, [
            PermissionKey::EventPlayerView,
            PermissionKey::EventPlayerCreate,
            PermissionKey::EventPlayerManage,
        ], true)) {
            return false;
        }

        if ((string) $actor->id === (string) $target->id) {
            return true;
        }

        if ($permission === PermissionKey::EventPlayerCreate) {
            return false;
        }

        $entries = AllianceRosterEntry::query()
            ->where('player_id', $target->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', $target->current_kingdom_id))
            ->with('alliance')
            ->get();

        foreach ($entries as $entry) {
            $alliance = $entry->alliance;
            if ($alliance instanceof Alliance
                && $this->allianceAuthorization->allows($actor, $alliance, PermissionKey::EventPlayerManage)) {
                return true;
            }
        }

        return false;
    }
}
