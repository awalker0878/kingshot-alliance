<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Enums;

use App\Shared\Infrastructure\Access\Contracts\Permission;

enum AlliancePermission: string implements Permission
{
    case View = 'alliance.view';
    case Manage = 'alliance.manage';
    case MembershipManage = 'membership.manage';
    case RoleManage = 'roles.manage';
    case InvitationManage = 'invitations.manage';
    case ContentManage = 'content.manage';
    case RecruitmentManage = 'recruitment.manage';
    case GiftCodeCoverage = 'gift_codes.coverage';

    public function key(): string
    {
        return $this->value;
    }
}
