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
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEvent
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
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
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        if ($availableFrom !== null && $availableUntil !== null && $availableUntil->lessThan($availableFrom)) {
            throw ValidationException::withMessages([
                'available_until' => 'Availability end must not be before availability start.',
            ]);
        }

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $response, $preferredRole, $preferredTeam, $availableFrom, $availableUntil, $note): EventResponse {
            $context = $this->mutations->requireSelf($actor, $event, $player);
            $this->capabilities->require($context->event, EventCapability::Responses);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $currentPlayer = $context->actor;

            $record = EventResponse::query()->updateOrCreate(
                [
                    'occurrence_id' => $lockedOccurrence->id,
                    'player_id' => $currentPlayer->id,
                ],
                [
                    'response' => $response,
                    'preferred_role' => $preferredRole === null || trim($preferredRole) === '' ? null : trim($preferredRole),
                    'preferred_team' => $preferredTeam === null || trim($preferredTeam) === '' ? null : trim($preferredTeam),
                    'available_from' => $availableFrom?->utc(),
                    'available_until' => $availableUntil?->utc(),
                    'note' => $note === null || trim($note) === '' ? null : trim($note),
                    'source' => EventResponseSource::Self,
                    'responded_by_player_id' => $currentPlayer->id,
                    'responded_at' => now(),
                ],
            );

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'response' => $response->value,
            ];
            $this->audit->record('event.response.changed', $currentPlayer, $record, $alliance, $metadata);
            $this->outbox->record(
                'event.response.changed',
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $record->refresh();
        });
    }
}
