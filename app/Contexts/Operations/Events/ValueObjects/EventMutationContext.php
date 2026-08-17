<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;

final readonly class EventMutationContext
{
    public function __construct(
        public Event $event,
        public EventTypeScope $typeScope,
        public Player $actor,
        public Alliance|Kingdom|Player $target,
    ) {}
}
