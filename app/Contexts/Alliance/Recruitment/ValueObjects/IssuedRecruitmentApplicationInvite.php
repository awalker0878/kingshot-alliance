<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\ValueObjects;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentApplicationInvite;

final readonly class IssuedRecruitmentApplicationInvite
{
    public function __construct(
        public RecruitmentApplicationInvite $invite,
        public string $token,
    ) {}
}
