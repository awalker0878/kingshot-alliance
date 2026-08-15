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
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Events\Services\EventPlayerContextFreezer;
use App\Domain\Events\Services\EventRegistrationWindow;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RegisterForEvent
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private EventCapabilityResolver $capabilityResolver,
        private EventRegistrationWindow $window,
        private EventPlayerContextFreezer $contexts,
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

            $window = $this->window->for($context->event, $lockedOccurrence);
            if (! $window['is_open']) {
                throw ValidationException::withMessages([
                    'registration' => 'Registration is not currently open for this occurrence.',
                ]);
            }

            $existing = EventRegistration::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->where('player_id', $currentPlayer->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof EventRegistration && $existing->status !== EventRegistrationStatus::Cancelled) {
                if ($existing->status === EventRegistrationStatus::Registered) {
                    $this->contexts->freeze($lockedOccurrence, $currentPlayer);
                }

                return $existing;
            }

            $registeredCount = EventRegistration::query()
                ->where('occurrence_id', $lockedOccurrence->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->count();
            $hasSeat = $context->event->capacity === null
                || $registeredCount < (int) $context->event->capacity;
            $status = EventRegistrationStatus::Registered;
            $waitlistPosition = null;

            if (! $hasSeat) {
                if (! $this->capabilityResolver->supports($context->typeScope, EventCapability::Waitlist)) {
                    throw ValidationException::withMessages(['registration' => 'This occurrence is full.']);
                }

                $status = EventRegistrationStatus::Waitlisted;
                $waitlistPosition = ((int) EventRegistration::query()
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->max('waitlist_position')) + 1;
            }

            $values = [
                'status' => $status,
                'waitlist_position' => $waitlistPosition,
                'registered_by_player_id' => $currentPlayer->id,
                'registered_at' => now(),
                'cancelled_by_player_id' => null,
                'cancelled_at' => null,
            ];

            if ($existing instanceof EventRegistration) {
                $existing->forceFill($values)->save();
                $registration = $existing;
            } else {
                $registration = EventRegistration::query()->create([
                    'occurrence_id' => $lockedOccurrence->id,
                    'player_id' => $currentPlayer->id,
                    ...$values,
                ]);
            }

            if ($status === EventRegistrationStatus::Registered) {
                $this->contexts->freeze($lockedOccurrence, $currentPlayer);
            }

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'status' => $status->value,
                'waitlist_position' => $waitlistPosition,
            ];
            $this->audit->record('event.registration.created', $currentPlayer, $registration, $alliance, $metadata);
            $this->outbox->record(
                'event.registration.created',
                $alliance?->id,
                $registration,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $registration->refresh();
        });
    }
}
