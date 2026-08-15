<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Enums;

use App\Shared\Access\Contracts\Permission;

enum AlliancePermission: string implements Permission
{
    case View = 'alliance.view';
    case Manage = 'alliance.manage';
    case MembershipManage = 'membership.manage';
    case RoleManage = 'roles.manage';
    case InvitationManage = 'invitations.manage';
    case ContentManage = 'content.manage';
    case RecruitmentManage = 'recruitment.manage';

    public function key(): string
    {
        return $this->value;
    }
}
