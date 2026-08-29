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
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelEventRegistration
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
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

            $registration = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $actorPlayerId)
                ->lockForUpdate()
                ->first();

            if (! $registration instanceof EventRegistration || $registration->status === EventRegistrationStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'registration' => 'This Player is not currently registered.',
                ]);
            }

            $wasRegistered = $registration->status === EventRegistrationStatus::Registered;
            $registration->forceFill([
                'status' => EventRegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_by_player_id' => $actorPlayerId,
                'cancelled_at' => now(),
            ])->save();

            $promoted = null;
            if ($wasRegistered) {
                $promoted = EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->orderBy('waitlist_position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($promoted instanceof EventRegistration) {
                    $promoted->forceFill(['status' => EventRegistrationStatus::Registered, 'waitlist_position' => null])->save();
                }
            }

            $remaining = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
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

            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => $actorPlayerId,
                'promoted_player_id' => $promoted?->player_id,
            ];
            $this->audit->record('event.registration.cancelled', $context->actor, $registration, $context->target->allianceId, $metadata);
            $this->outbox->record('event.registration.cancelled', $context->target->allianceId, $registration, $metadata, partitionKey: $context->target->partitionKey());

            if ($promoted instanceof EventRegistration) {
                $promotionMetadata = [
                    'occurrence_id' => (string) $occurrence->id,
                    'player_id' => (string) $promoted->player_id,
                    'promoted_after_player_id' => $actorPlayerId,
                ];
                $this->audit->record('event.registration.promoted', $context->actor, $promoted, $context->target->allianceId, $promotionMetadata);
                $this->outbox->record('event.registration.promoted', $context->target->allianceId, $promoted, $promotionMetadata, partitionKey: $context->target->partitionKey());
            }
        });
    }
}
