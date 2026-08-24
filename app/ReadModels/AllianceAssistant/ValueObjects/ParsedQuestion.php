<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\ValueObjects;

use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactRequest;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;

final readonly class ParsedQuestion
{
    public function __construct(
        public AssistantIntent $intent,
        public ?string $subject = null,
        public bool $includeEventTime = false,
        public bool $nextEvent = false,
        public ?ProgressionFactRequest $gameFact = null,
        public bool $thisWeek = false,
        public ?int $kingdomNumber = null,
        public ?string $writeAction = null,
    ) {}
}
