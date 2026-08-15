<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventRegistration;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelEventRegistration
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventOccurrence $occurrence, Player $player): EventRegistration
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        return DB::transaction(function () use ($actor, $occurrence, $event, $player): EventRegistration {
            $context = $this->mutations->requireSelf($actor, $event, $player);
            $this->capabilities->require($context->event, EventCapability::Registration);

            $currentPlayer = $context->actor;
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $registration = EventRegistration::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->where('player_id', $currentPlayer->id)
                ->lockForUpdate()
                ->first();

            if (! $registration instanceof EventRegistration
                || $registration->status === EventRegistrationStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'registration' => 'This Player is not currently registered.',
                ]);
            }

            $wasRegistered = $registration->status === EventRegistrationStatus::Registered;
            $registration->forceFill([
                'status' => EventRegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_by_player_id' => $currentPlayer->id,
                'cancelled_at' => now(),
            ])->save();

            $promoted = null;
            if ($wasRegistered) {
                $promoted = EventRegistration::query()
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->orderBy('waitlist_position')
                    ->orderBy('id')
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
                ->where('occurrence_id', $lockedOccurrence->id)
                ->where('status', EventRegistrationStatus::Waitlisted->value)
                ->orderBy('waitlist_position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($remaining as $index => $waitlisted) {
                $position = $index + 1;
                if ((int) $waitlisted->waitlist_position !== $position) {
                    $waitlisted->forceFill(['waitlist_position' => $position])->save();
                }
            }

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'promoted_player_id' => $promoted?->player_id,
            ];
            $this->audit->record('event.registration.cancelled', $currentPlayer, $registration, $alliance, $metadata);
            $this->outbox->record(
                'event.registration.cancelled',
                $alliance?->id,
                $registration,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            if ($promoted instanceof EventRegistration) {
                $promotionMetadata = [
                    'occurrence_id' => (string) $lockedOccurrence->id,
                    'player_id' => (string) $promoted->player_id,
                    'promoted_after_player_id' => (string) $currentPlayer->id,
                ];
                $this->audit->record('event.registration.promoted', $currentPlayer, $promoted, $alliance, $promotionMetadata);
                $this->outbox->record(
                    'event.registration.promoted',
                    $alliance?->id,
                    $promoted,
                    $promotionMetadata,
                    partitionKey: $context->event->scope->value.':'.$context->target->id,
                );
            }

            return $registration->refresh();
        });
    }
}
