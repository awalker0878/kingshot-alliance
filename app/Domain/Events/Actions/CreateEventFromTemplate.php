<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class CreateEventFromTemplate
{
    public function __construct(private CreateEvent $createEvent) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        EventTemplate $template,
        ?CarbonImmutable $firstLocalStart,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?string $title = null,
        bool $publish = true,
    ): Event {
        if ($template->alliance_id !== $alliance->id || ! $template->is_active) {
            throw new AuthorizationException('The event template is not available in the active alliance.');
        }

        if (! $firstLocalStart instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Event start date is required.');
        }

        return $this->createEvent->handle(
            actor: $actor,
            alliance: $alliance,
            title: $title === null || trim($title) === '' ? (string) $template->name : $title,
            firstLocalStart: $firstLocalStart,
            durationMinutes: (int) $template->duration_minutes,
            capacity: $template->capacity === null ? null : (int) $template->capacity,
            registrationOpensMinutesBefore: $template->registration_opens_minutes_before === null
                ? null
                : (int) $template->registration_opens_minutes_before,
            registrationClosesMinutesBefore: (int) $template->registration_closes_minutes_before,
            frequency: $template->recurrence_frequency,
            recurrenceInterval: (int) $template->recurrence_interval,
            recurrenceUntilLocal: $recurrenceUntilLocal,
            instructions: $template->instructions,
            publish: $publish,
            template: $template,
        );
    }
}
