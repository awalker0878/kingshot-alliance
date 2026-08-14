<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordRallyParticipation
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, RallyAssignment $assignment, RallyAssignmentStatus $status): RallyAssignment
    {
        if (! in_array($status, [RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
            throw ValidationException::withMessages(['status' => 'Participation must be recorded as participated or absent.']);
        }
        $assignment->loadMissing('rallyGroup.occurrence.event.typeScope', 'rallyGroup.alliance');
        $group = $assignment->rallyGroup;
        $event = $group->occurrence->event;
        $this->capabilities->require($event, EventCapability::RallyGuidance);
        $this->authorization->authorizeManager($actor, $event);
        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $assignment, $status, $group, $event, $target): RallyAssignment {
            $locked = RallyAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [RallyAssignmentStatus::Declined, RallyAssignmentStatus::Removed], true)) {
                throw ValidationException::withMessages(['status' => 'Declined or removed assignments cannot receive participation.']);
            }
            $locked->forceFill([
                'status' => $status,
                'recorded_by_player_id' => $actor->id,
                'recorded_at' => now(),
            ])->save();
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $group->occurrence_id,
                'alliance_id' => (string) $group->alliance_id,
                'rally_group_id' => (string) $group->id,
                'player_id' => (string) $locked->player_id,
                'status' => $status->value,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('rally.participation.recorded', $actor, $locked, $group->alliance, $metadata);
            $this->outbox->record('rally.participation.recorded', (string) $group->alliance_id, $locked, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $locked->refresh();
        });
    }
}
