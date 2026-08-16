<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventPhaseStatus;
use App\Contexts\Operations\EventCore\Enums\EventPhaseType;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventPhase;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPhase
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        string $key,
        EventPhaseType $type,
        ?string $name = null,
        ?string $nameKey = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        EventPhaseStatus $status = EventPhaseStatus::Scheduled,
        int $sortOrder = 0,
        array $settings = [],
        ?EventPhase $phase = null,
    ): EventPhase {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        if ($phase instanceof EventPhase && (string) $phase->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }
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

        return DB::transaction(function () use ($actor, $occurrence, $event, $phase, $key, $type, $name, $nameKey, $startsAt, $endsAt, $status, $sortOrder, $settings): EventPhase {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Phases);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $record = $phase instanceof EventPhase
                ? EventPhase::query()
                    ->whereKey($phase->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new EventPhase(['occurrence_id' => $lockedOccurrence->id]);

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->id;
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
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'phase_key' => $key,
                'phase_type' => $type->value,
                'status' => $status->value,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $eventName = $created ? 'event.phase.created' : 'event.phase.updated';
            $this->audit->record($eventName, $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $record->refresh();
        });
    }
}
