<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventPhaseStatus;
use App\Domain\Events\Enums\EventPhaseType;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPhase;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPhase
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
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
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Phases);
        $this->authorization->authorizeManager($actor, $event);

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

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $phase, $key, $type, $name, $nameKey, $startsAt, $endsAt, $status, $sortOrder, $settings, $target): EventPhase {
            $record = $phase instanceof EventPhase
                ? EventPhase::query()->whereKey($phase->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventPhase(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->id;
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
                'updated_by_player_id' => $actor->id,
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'phase_key' => $key,
                'phase_type' => $type->value,
                'status' => $status->value,
                'actor_player_id' => $actor->id,
            ];
            $eventName = $created ? 'event.phase.created' : 'event.phase.updated';
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }
}
