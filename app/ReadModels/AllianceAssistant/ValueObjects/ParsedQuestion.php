<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\ValueObjects;

use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;

final readonly class ParsedQuestion
{
    public function __construct(
        public AssistantIntent $intent,
        public ?string $subject = null,
        public bool $includeEventTime = false,
        public bool $nextEvent = false,
    ) {}
}
