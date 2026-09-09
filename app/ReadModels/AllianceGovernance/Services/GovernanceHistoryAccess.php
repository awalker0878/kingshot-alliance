<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class GovernanceHistoryAccess
{
    public function __construct(private AllianceAuthorization $authorization) {}

    public function allows(string $playerId, string $allianceId): bool
    {
        return $this->authorization->allows($playerId, $allianceId, AlliancePermission::MembershipManage)
            || $this->authorization->allows($playerId, $allianceId, AlliancePermission::RoleManage)
            || $this->authorization->allows($playerId, $allianceId, AlliancePermission::Manage);
    }

    public function authorize(string $playerId, string $allianceId): void
    {
        if (! $this->allows($playerId, $allianceId)) {
            throw new AuthorizationException;
        }
    }
}
