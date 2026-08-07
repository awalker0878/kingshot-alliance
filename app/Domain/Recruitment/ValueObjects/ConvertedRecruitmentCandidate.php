<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\ValueObjects;

use App\Domain\Memberships\Models\Invitation;
use App\Domain\Recruitment\Models\RecruitmentCandidate;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public RecruitmentCandidate $candidate,
        public Invitation $invitation,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
