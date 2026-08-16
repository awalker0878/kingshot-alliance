<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveRallyPlayer
{
    public function __construct(
        private EventMutationAuthority $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, RallyAssignment $assignment): RallyAssignment
    {
        $assignment->loadMissing('rallyGroup.occurrence.event');
        $group = $assignment->rallyGroup;
        $event = $group->occurrence->event;

        return DB::transaction(function () use ($actor, $assignment, $group, $event): RallyAssignment {
            $context = $this->eventAuthority->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            // Removal changes only one assignment. Shared occurrence/group locks make
            // lifecycle/context stable and block occurrence-wide placement mutations.
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

            if ($locked->status === RallyAssignmentStatus::Removed) {
                return $locked;
            }

            $locked->forceFill([
                'status' => RallyAssignmentStatus::Removed,
                'slot_number' => null,
                'removed_by_player_id' => $context->actor->id,
                'removed_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => (string) $lockedGroup->alliance_id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $locked->player_id,
                'actor_player_id' => $context->actor->id,
            ];
            $this->audit->record('rally.assignment.removed', $context->actor, $locked, $lockedGroup->alliance, $metadata);
            $this->outbox->record(
                'rally.assignment.removed',
                (string) $lockedGroup->alliance_id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $locked->refresh();
        });
    }
}
