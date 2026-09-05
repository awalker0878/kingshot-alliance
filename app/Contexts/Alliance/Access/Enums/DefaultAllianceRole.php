<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Enums;

enum DefaultAllianceRole: string
{
    case Recruiter = 'recruiter';
    case EventCoordinator = 'event_coordinator';
    case ContentManager = 'content_manager';
    case GiftCodeCoordinator = 'gift_code_coordinator';

    public function name(): string
    {
        return match ($this) {
            self::Recruiter => 'Recruiter',
            self::EventCoordinator => 'Event Coordinator',
            self::ContentManager => 'Content Manager',
            self::GiftCodeCoordinator => 'Gift Code Coordinator',
        };
    }
}
