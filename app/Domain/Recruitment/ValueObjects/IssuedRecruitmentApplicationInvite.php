<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\ValueObjects;

use App\Domain\Recruitment\Models\RecruitmentApplicationInvite;

final readonly class IssuedRecruitmentApplicationInvite
{
    public function __construct(
        public RecruitmentApplicationInvite $invite,
        public string $token,
    ) {}
}
