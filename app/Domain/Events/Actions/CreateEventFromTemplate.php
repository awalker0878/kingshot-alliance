<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Kingdoms\Models\Player;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Services\EventTargetResolver;
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

        return $this->create->handle(
            actor: $actor,
            configuration: $template->typeScope,
            target: $this->targets->forTemplate($template),
            firstLocalStart: $firstLocalStart,
            title: $title,
            instructions: $template->instructions,
            durationMinutes: $template->duration_minutes,
            capacity: $template->capacity,
            registrationOpensMinutesBefore: $template->registration_opens_minutes_before,
            registrationClosesMinutesBefore: $template->registration_closes_minutes_before,
            frequency: $template->recurrence_frequency,
            recurrenceInterval: $template->recurrence_interval,
            recurrenceUntilLocal: $recurrenceUntilLocal,
            settings: $template->settings ?? [],
            publish: $publish,
            template: $template,
        );
    }
}
