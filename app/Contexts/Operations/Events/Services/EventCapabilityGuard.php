<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\Event;
use Illuminate\Validation\ValidationException;

final readonly class EventCapabilityGuard
{
    public function __construct(private EventCapabilityResolver $resolver) {}

    public function require(Event $event, EventCapability $capability): void
    {
        $event->loadMissing('typeScope');
        if (! $this->resolver->supports($event->typeScope, $capability)) {
            throw ValidationException::withMessages([
                'event' => sprintf('This Event does not support %s.', str_replace('_', ' ', $capability->value)),
            ]);
        }
    }
}
