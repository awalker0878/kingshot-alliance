<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use Carbon\CarbonImmutable;

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

        // The template object is only routing input here. CreateEvent re-resolves and
        // shared-locks the current template row before reading any mutable defaults.
        return $this->create->handle(
            actor: $actor,
            configuration: $template->typeScope,
            target: $this->targets->forTemplate($template),
            firstLocalStart: $firstLocalStart,
            title: $title,
            recurrenceUntilLocal: $recurrenceUntilLocal,
            publish: $publish,
            template: $template,
        );
    }
}
