<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;

/**
 * Transaction-time Event mutation state.
 *
 * Eloquent models in this object are Operations-owned rows loaded by the
 * protected operation itself. Cross-context identity and authority are
 * immutable references/facts acquired from their owning contexts.
 */
final readonly class EventMutationContext
{
    public function __construct(
        public Event $event,
        public EventTypeScope $typeScope,
        public PlayerReference $actor,
        public EventTargetReference $target,
        public EventScopeAuthorityFacts $authority,
    ) {}
}
