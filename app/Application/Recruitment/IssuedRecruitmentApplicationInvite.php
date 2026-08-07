<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Models\RecruitmentApplicationInvite;

final readonly class IssuedRecruitmentApplicationInvite
{
    public function __construct(
        public RecruitmentApplicationInvite $invite,
        public string $token,
    ) {}
}
