<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use App\Domain\Events\Models\Event;

enum ContentType: string
{
    case Announcement = 'announcement';
    case Guide = 'guide';
    case Rule = 'rule';
    case EventInstruction = 'event_instruction';
    case ReferencePage = 'reference_page';

    public function label(): string
    {
        return match ($this) {
            self::Announcement => 'Announcement',
            self::Guide => 'Guide',
            self::Rule => 'Rule',
            self::EventInstruction => 'Event instruction',
            self::ReferencePage => 'Reference page',
        };
    }
}
