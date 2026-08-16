<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;

final readonly class AlliancePermissionQuery
{
    public function __construct(private AllianceAuthorization $authorization) {}

    public function allows(
        string $playerId,
        string $allianceId,
        AlliancePermission $permission,
    ): bool {
        $player = Player::query()->find($playerId);
        $alliance = Alliance::query()->find($allianceId);

        if (! $player instanceof Player || ! $alliance instanceof Alliance) {
            return false;
        }

        return $this->authorization->allows($player, $alliance, $permission);
    }
}
