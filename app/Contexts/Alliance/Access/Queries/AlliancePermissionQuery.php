<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;

final readonly class AlliancePermissionQuery
{
    public function __construct(private AllianceAuthorization $authorization) {}

    public function allows(
        string $playerId,
        string $allianceId,
        AlliancePermission $permission,
    ): bool {
        return $this->authorization->allows($playerId, $allianceId, $permission);
    }
}
