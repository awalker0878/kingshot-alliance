<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Services;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use LogicException;

final class AllianceContext
{
    private ?Alliance $alliance = null;
    private ?AllianceMembership $membership = null;
    private ?Player $player = null;

    public function activate(Player $player, AllianceMembership $membership, Alliance $alliance): void
    {
        if ((string) $membership->player_id !== (string) $player->id || (string) $membership->alliance_id !== (string) $alliance->id || (string) $player->current_kingdom_id !== (string) $alliance->kingdom_id) {
            throw new LogicException('Alliance context must match the active Player membership and Kingdom.');
        }
        $this->player = $player; $this->membership = $membership; $this->alliance = $alliance;
    }

    public function player(): Player { return $this->player ?? throw new LogicException('Alliance Player context has not been resolved.'); }
    public function alliance(): Alliance { return $this->alliance ?? throw new LogicException('Alliance context has not been resolved.'); }
    public function membership(): AllianceMembership { return $this->membership ?? throw new LogicException('Alliance membership context has not been resolved.'); }
    public function clear(): void { $this->player = null; $this->alliance = null; $this->membership = null; }
}
