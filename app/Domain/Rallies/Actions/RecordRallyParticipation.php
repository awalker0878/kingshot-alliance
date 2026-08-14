<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordRallyParticipation
{
    public function __construct(
        private EventMutationAuthority $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, RallyAssignment $assignment, RallyAssignmentStatus $status): RallyAssignment
    {
        if (! in_array($status, [RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
            throw ValidationException::withMessages(['status' => 'Participation must be recorded as participated or absent.']);
        }

        $assignment->loadMissing('rallyGroup.occurrence.event');
        $group = $assignment->rallyGroup;
        $event = $group->occurrence->event;

        return DB::transaction(function () use ($actor, $assignment, $status, $group, $event): RallyAssignment {
            $context = $this->eventAuthority->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            // Participation only changes one assignment. Share-lock occurrence/group
            // lifecycle; keep the assignment itself exclusive.
            $occurrence = EventOccurrence::query()
                ->whereKey($group->occurrence_id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $lockedGroup = RallyGroup::query()
                ->whereKey($group->id)
                ->where('occurrence_id', $occurrence->id)
                ->sharedLock()
                ->firstOrFail()
                ->load('alliance');

            $locked = RallyAssignment::query()
                ->whereKey($assignment->id)
                ->where('rally_group_id', $lockedGroup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [RallyAssignmentStatus::Declined, RallyAssignmentStatus::Removed], true)) {
                throw ValidationException::withMessages(['status' => 'Declined or removed assignments cannot receive participation.']);
            }

            $locked->forceFill([
                'status' => $status,
                'recorded_by_player_id' => $context->actor->id,
                'recorded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedGroup->occurrence_id,
                'alliance_id' => (string) $lockedGroup->alliance_id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $locked->player_id,
                'status' => $status->value,
                'actor_player_id' => $context->actor->id,
            ];
            $this->audit->record('rally.participation.recorded', $context->actor, $locked, $lockedGroup->alliance, $metadata);
            $this->outbox->record(
                'rally.participation.recorded',
                (string) $lockedGroup->alliance_id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $locked->refresh();
        });
    }
}
