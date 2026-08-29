<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventPhaseStatus;
use App\Contexts\Operations\Events\Enums\EventPhaseType;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPhase
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $key,
        EventPhaseType $type,
        ?string $name = null,
        ?string $nameKey = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        EventPhaseStatus $status = EventPhaseStatus::Scheduled,
        int $sortOrder = 0,
        array $settings = [],
        ?string $phaseId = null,
    ): void {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key)) {
            throw ValidationException::withMessages(['key' => 'Phase key must use lowercase letters, numbers, and hyphens.']);
        }
        if (($name === null || trim($name) === '') && ($nameKey === null || trim($nameKey) === '')) {
            throw ValidationException::withMessages(['name' => 'A phase name is required.']);
        }
        if (($startsAt === null) !== ($endsAt === null)) {
            throw ValidationException::withMessages(['starts_at' => 'Phase start and end must both be provided or both be empty.']);
        }
        if ($startsAt !== null && $endsAt !== null && ! $endsAt->greaterThan($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'Phase end must be after phase start.']);
        }

        DB::transaction(function () use (
            $actorPlayerId,
            $occurrenceId,
            $phaseId,
            $key,
            $type,
            $name,
            $nameKey,
            $startsAt,
            $endsAt,
            $status,
            $sortOrder,
            $settings,
        ): void {
            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->sharedLock()->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $occurrence->event_id);
            $this->mutations->authorizeManager($context);
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $record = $phaseId === null
                ? new EventPhase(['occurrence_id' => $lockedOccurrence->id])
                : EventPhase::query()
                    ->whereKey($phaseId)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->playerId;
            }

            $record->forceFill([
                'key' => $key,
                'name_key' => $nameKey === null || trim($nameKey) === '' ? null : trim($nameKey),
                'name' => $name === null || trim($name) === '' ? null : trim($name),
                'phase_type' => $type,
                'starts_at' => $startsAt?->utc(),
                'ends_at' => $endsAt?->utc(),
                'status' => $status,
                'sort_order' => max(0, $sortOrder),
                'settings' => ['source' => 'manual'] + $settings,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'phase_key' => $key,
                'phase_type' => $type->value,
                'status' => $status->value,
                'actor_player_id' => $context->actor->playerId,
            ];
            $eventName = $created ? 'event.phase.created' : 'event.phase.updated';
            $this->audit->record($eventName, $context->actor, $record, metadata: $metadata);
            $this->outbox->record(
                $eventName,
                $context->target->allianceId,
                $record,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
