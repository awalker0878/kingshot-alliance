<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\GameWorld\Models\Player;

final readonly class ActivePlayerEventVisibilityResolver
{
    public function __construct(private EventVisibilityResolver $visibility) {}

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    public function targetIds(Player $actor): array
    {
        return $this->visibility->targetIds($actor);
    }
}
