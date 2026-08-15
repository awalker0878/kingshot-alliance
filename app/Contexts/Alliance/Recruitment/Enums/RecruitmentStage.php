<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Enums;

enum RecruitmentStage: string
{
    case New = 'new';
    case Screening = 'screening';
    case Contacted = 'contacted';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Joined = 'joined';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Screening, self::Contacted, self::Declined, self::Withdrawn],
            self::Screening => [self::Contacted, self::Interview, self::Accepted, self::Declined, self::Withdrawn],
            self::Contacted => [self::Screening, self::Interview, self::Accepted, self::Declined, self::Withdrawn],
            self::Interview => [self::Contacted, self::Accepted, self::Declined, self::Withdrawn],
            self::Accepted => [self::Joined, self::Declined, self::Withdrawn],
            self::Declined => [self::Screening],
            self::Withdrawn => [self::Screening],
            self::Joined => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isUnsuccessful(): bool
    {
        return in_array($this, [self::Declined, self::Withdrawn], true);
    }
}
