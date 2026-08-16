<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;

final class PlayerEventAuthorization
{
    public function __construct(private AllianceOperationsAuthorization $allianceAuthorization) {}

    public function allows(Player $actor, Player $target, OperationsPermission $permission): bool
    {
        if (! in_array($permission, [
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventPlayerManage,
        ], true)) {
            return false;
        }

        if ((string) $actor->id === (string) $target->id) {
            return true;
        }

        if ($permission === OperationsPermission::EventPlayerCreate) {
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
                && $this->allianceAuthorization->allows($actor, $alliance, OperationsPermission::EventPlayerManage)) {
                return true;
            }
        }

        return false;
    }
}
