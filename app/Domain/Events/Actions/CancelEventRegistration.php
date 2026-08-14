<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelEventRegistration
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventOccurrence $occurrence, Player $player): EventRegistration
    {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Registration);
        $this->authorization->authorizeSelf($actor, $event, $player);

        return DB::transaction(function () use ($actor, $occurrence, $event, $player): EventRegistration {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $registration = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->first();

            if (! $registration instanceof EventRegistration || $registration->status === EventRegistrationStatus::Cancelled) {
                throw ValidationException::withMessages(['registration' => 'This Player is not currently registered.']);
            }

            $wasRegistered = $registration->status === EventRegistrationStatus::Registered;
            $registration->forceFill([
                'status' => EventRegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_by_player_id' => $player->id,
                'cancelled_at' => now(),
            ])->save();

            $promoted = null;
            if ($wasRegistered) {
                $promoted = EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->orderBy('waitlist_position')
                    ->lockForUpdate()
                    ->first();

                if ($promoted instanceof EventRegistration) {
                    $promoted->forceFill([
                        'status' => EventRegistrationStatus::Registered,
                        'waitlist_position' => null,
                    ])->save();
                }
            }

            $remaining = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', EventRegistrationStatus::Waitlisted->value)
                ->orderBy('waitlist_position')
                ->lockForUpdate()
                ->get();
            foreach ($remaining as $index => $waitlisted) {
                $position = $index + 1;
                if ((int) $waitlisted->waitlist_position !== $position) {
                    $waitlisted->forceFill(['waitlist_position' => $position])->save();
                }
            }

            $target = $this->targets->forEvent($event);
            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => (string) $player->id,
                'promoted_player_id' => $promoted?->player_id,
            ];
            $this->audit->record('event.registration.cancelled', $actor, $registration, $alliance, $metadata);
            $this->outbox->record('event.registration.cancelled', $alliance?->id, $registration, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            if ($promoted instanceof EventRegistration) {
                $promotionMetadata = [
                    'occurrence_id' => (string) $occurrence->id,
                    'player_id' => (string) $promoted->player_id,
                    'promoted_after_player_id' => (string) $player->id,
                ];
                $this->audit->record('event.registration.promoted', $actor, $promoted, $alliance, $promotionMetadata);
                $this->outbox->record(
                    'event.registration.promoted',
                    $alliance?->id,
                    $promoted,
                    $promotionMetadata,
                    partitionKey: $event->scope->value.':'.$target->id,
                );
            }

            return $registration->refresh();
        });
    }
}
