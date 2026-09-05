<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;

final class AllianceDefaultRolePermissions
{
    /** @return list<AlliancePermission> */
    public function for(DefaultAllianceRole $role): array
    {
        return match ($role) {
            DefaultAllianceRole::Recruiter => [
                AlliancePermission::InvitationManage,
                AlliancePermission::RecruitmentManage,
            ],
            DefaultAllianceRole::EventCoordinator => [],
            DefaultAllianceRole::ContentManager => [AlliancePermission::ContentManage],
            DefaultAllianceRole::GiftCodeCoordinator => [AlliancePermission::GiftCodeCoverage],
        };
    }
}
