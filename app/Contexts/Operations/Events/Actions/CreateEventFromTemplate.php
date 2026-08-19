<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTemplate;
use App\Contexts\Operations\Events\ValueObjects\CreatedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateEventFromTemplate
{
    public function __construct(private CreateEvent $create) {}

    public function handle(
        string $actorPlayerId,
        string $templateId,
        CarbonImmutable $firstLocalStart,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?string $title = null,
        bool $publish = true,
    ): CreatedEvent {
        return DB::transaction(function () use (
            $actorPlayerId,
            $templateId,
            $firstLocalStart,
            $recurrenceUntilLocal,
            $title,
            $publish,
        ): CreatedEvent {
            $template = EventTemplate::query()
                ->whereKey($templateId)
                ->where('is_active', true)
                ->sharedLock()
                ->firstOrFail();
            $scope = $template->scopeEnum();
            $targetId = match ($scope) {
                EventScope::Alliance => $template->alliance_id,
                EventScope::Kingdom => $template->kingdom_id,
                EventScope::Player => $template->player_id,
            };
            if (! is_string($targetId) || $targetId === '') {
                throw new LogicException('Event template must contain a valid target identity.');
            }

            return $this->create->handle(
                actorPlayerId: $actorPlayerId,
                configurationId: (string) $template->event_type_scope_id,
                scope: $scope,
                targetId: $targetId,
                firstLocalStart: $firstLocalStart,
                title: $title,
                recurrenceUntilLocal: $recurrenceUntilLocal,
                publish: $publish,
                templateId: (string) $template->id,
            );
        });
    }
}
