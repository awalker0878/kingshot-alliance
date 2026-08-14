<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventResponseChoice;
use App\Domain\Events\Enums\EventResponseSource;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventResponse;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEvent
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        Player $player,
        EventResponseChoice $response,
        ?string $preferredRole = null,
        ?string $preferredTeam = null,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableUntil = null,
        ?string $note = null,
    ): EventResponse {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Responses);
        $this->authorization->authorizeSelf($actor, $event, $player);

        if ($availableFrom !== null && $availableUntil !== null && $availableUntil->lessThan($availableFrom)) {
            throw ValidationException::withMessages(['available_until' => 'Availability end must not be before availability start.']);
        }

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $response, $preferredRole, $preferredTeam, $availableFrom, $availableUntil, $note): EventResponse {
            $record = EventResponse::query()->updateOrCreate(
                ['occurrence_id' => $occurrence->id, 'player_id' => $player->id],
                [
                    'response' => $response,
                    'preferred_role' => $preferredRole === null || trim($preferredRole) === '' ? null : trim($preferredRole),
                    'preferred_team' => $preferredTeam === null || trim($preferredTeam) === '' ? null : trim($preferredTeam),
                    'available_from' => $availableFrom?->utc(),
                    'available_until' => $availableUntil?->utc(),
                    'note' => $note === null || trim($note) === '' ? null : trim($note),
                    'source' => EventResponseSource::Self,
                    'responded_by_player_id' => $player->id,
                    'responded_at' => now(),
                ],
            );

            $target = $this->targets->forEvent($event);
            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => (string) $player->id,
                'response' => $response->value,
            ];
            $this->audit->record('event.response.changed', $actor, $record, $alliance, $metadata);
            $this->outbox->record('event.response.changed', $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }
}
