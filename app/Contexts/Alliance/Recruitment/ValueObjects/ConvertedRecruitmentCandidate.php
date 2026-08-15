<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\ValueObjects;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public RecruitmentCandidate $candidate,
        public string $invitationId,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
