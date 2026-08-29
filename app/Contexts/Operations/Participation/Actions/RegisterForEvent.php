<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Participation\Services\EventRegistrationWindow;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RegisterForEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private EventRegistrationWindow $window,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId): void
    {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockSelfScope($actorPlayerId, (string) $route->event_id, $actorPlayerId);
            $this->authorization->authorizeSelf($context, $actorPlayerId);
            $this->workflows->require($context->event, EventWorkflowDimension::Participation);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $window = $this->window->for($context->event, $occurrence);
            if (! $window['is_open']) {
                throw ValidationException::withMessages([
                    'registration' => 'Registration is not currently open for this occurrence.',
                ]);
            }

            $existing = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $actorPlayerId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof EventRegistration && $existing->statusEnum() !== EventRegistrationStatus::Cancelled) {
                if ($existing->statusEnum() === EventRegistrationStatus::Registered) {
                    $this->contexts->freeze($occurrence, $context->actor);
                }

                return;
            }

            $registeredCount = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->count();
            $hasSeat = $context->event->capacity === null || $registeredCount < (int) $context->event->capacity;
            $status = EventRegistrationStatus::Registered;
            $waitlistPosition = null;

            if (! $hasSeat) {
                $status = EventRegistrationStatus::Waitlisted;
                $waitlistPosition = ((int) EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->max('waitlist_position')) + 1;
            }

            $values = [
                'status' => $status,
                'waitlist_position' => $waitlistPosition,
                'registered_by_player_id' => $actorPlayerId,
                'registered_at' => now(),
                'cancelled_by_player_id' => null,
                'cancelled_at' => null,
            ];

            if ($existing instanceof EventRegistration) {
                $existing->forceFill($values)->save();
                $registration = $existing;
            } else {
                $registration = EventRegistration::query()->create([
                    'occurrence_id' => $occurrence->id,
                    'player_id' => $actorPlayerId,
                    ...$values,
                ]);
            }

            if ($status === EventRegistrationStatus::Registered) {
                $this->contexts->freeze($occurrence, $context->actor);
            }

            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => $actorPlayerId,
                'status' => $status->value,
                'waitlist_position' => $waitlistPosition,
            ];
            $this->audit->record('event.registration.created', $context->actor, $registration, $context->target->allianceId, $metadata);
            $this->outbox->record(
                'event.registration.created',
                $context->target->allianceId,
                $registration,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
