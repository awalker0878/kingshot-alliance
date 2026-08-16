<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Actions;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventTemplate;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\EventCore\Services\EventTargetResolver;
use Carbon\CarbonImmutable;
use LogicException;

final class CreateEventFromTemplate
{
    public function __construct(
        private CreateEvent $create,
        private EventTargetResolver $targets,
    ) {}

    public function handle(
        Player $actor,
        EventTemplate $template,
        CarbonImmutable $firstLocalStart,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?string $title = null,
        bool $publish = true,
    ): Event {
        $template->loadMissing('typeScope');
        $configuration = $template->typeScope;
        if (! $configuration instanceof EventTypeScope) {
            throw new LogicException('Event template must reference an Event type scope configuration.');
        }

        // The template object is only routing input here. CreateEvent re-resolves and
        // shared-locks the current template row before reading any mutable defaults.
        return $this->create->handle(
            actor: $actor,
            configuration: $configuration,
            target: $this->targets->forTemplate($template),
            firstLocalStart: $firstLocalStart,
            title: $title,
            recurrenceUntilLocal: $recurrenceUntilLocal,
            publish: $publish,
            template: $template,
        );
    }
}
