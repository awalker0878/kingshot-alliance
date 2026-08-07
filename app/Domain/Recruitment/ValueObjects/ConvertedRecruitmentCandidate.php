<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\ValueObjects;

use App\Domain\Recruitment\Models\RecruitmentCandidate;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public RecruitmentCandidate $candidate,
        public string $invitationId,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
