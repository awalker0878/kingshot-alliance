<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Models\Invitation;
use App\Models\RecruitmentCandidate;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public RecruitmentCandidate $candidate,
        public Invitation $invitation,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
