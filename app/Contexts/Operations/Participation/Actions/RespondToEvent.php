<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        EventResponseChoice $response,
        ?string $preferredRole = null,
        ?string $preferredTeam = null,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableUntil = null,
        ?string $note = null,
        EventResponseSource $source = EventResponseSource::Self,
    ): void {
        if ($availableFrom !== null && $availableUntil !== null && $availableUntil->lessThan($availableFrom)) {
            throw ValidationException::withMessages([
                'available_until' => 'Availability end must not be before availability start.',
            ]);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $response, $preferredRole, $preferredTeam, $availableFrom, $availableUntil, $note, $source): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockSelfScope($actorPlayerId, (string) $route->event_id, $actorPlayerId);
            $this->authorization->authorizeSelf($context, $actorPlayerId);
            $this->workflows->require($context->event, EventWorkflowDimension::Participation);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $record = EventResponse::query()->updateOrCreate(
                ['occurrence_id' => $occurrence->id, 'player_id' => $actorPlayerId],
                [
                    'response' => $response,
                    'preferred_role' => $preferredRole === null || trim($preferredRole) === '' ? null : trim($preferredRole),
                    'preferred_team' => $preferredTeam === null || trim($preferredTeam) === '' ? null : trim($preferredTeam),
                    'available_from' => $availableFrom?->utc(),
                    'available_until' => $availableUntil?->utc(),
                    'note' => $note === null || trim($note) === '' ? null : trim($note),
                    'source' => $source,
                    'responded_by_player_id' => $actorPlayerId,
                    'responded_at' => now(),
                ],
            );

            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => $actorPlayerId,
                'response' => $response->value,
                'source' => $source->value,
            ];
            $this->audit->record('event.response.changed', $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record('event.response.changed', $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
