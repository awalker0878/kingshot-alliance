<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\ValueObjects;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public string $candidateId,
        public string $invitationId,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
