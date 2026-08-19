<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum ContentType: string
{
    case Announcement = 'announcement';
    case Guide = 'guide';
    case Rule = 'rule';
    case EventInstruction = 'event_instruction';
    case ReferencePage = 'reference_page';

    public function requiresProvenance(): bool
    {
        return match ($this) {
            self::Guide, self::EventInstruction, self::ReferencePage => true,
            self::Announcement, self::Rule => false,
        };
    }

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
