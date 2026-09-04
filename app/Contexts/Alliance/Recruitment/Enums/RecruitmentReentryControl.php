<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Enums;

enum RecruitmentReentryControl: string
{
    case Normal = 'normal';
    case DoNotInvite = 'do_not_invite';
    case ReapplyAfter = 'reapply_after';
    case ReviewRequired = 'review_required';

    public function severity(): int
    {
        return match ($this) {
            self::DoNotInvite => 3,
            self::ReviewRequired => 2,
            self::ReapplyAfter => 1,
            self::Normal => 0,
        };
    }
}
