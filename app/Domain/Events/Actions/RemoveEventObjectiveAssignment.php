<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventObjectiveAssignment;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventObjectiveAssignment
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventObjectiveAssignment $assignment): void
    {
        $assignment->loadMissing('objective.occurrence.event.typeScope');
        $event = $assignment->objective->occurrence->event;
        $this->capabilities->require($event, EventCapability::Objectives);
        $this->authorization->authorizeManager($actor, $event);
        $target = $this->targets->forEvent($event);

        DB::transaction(function () use ($actor, $assignment, $event, $target): void {
            $locked = EventObjectiveAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $locked->occurrence_id,
                'objective_id' => (string) $locked->objective_id,
                'assignment_id' => (string) $locked->id,
                'roster_id' => $locked->roster_id === null ? null : (string) $locked->roster_id,
                'player_id' => $locked->player_id === null ? null : (string) $locked->player_id,
                'actor_player_id' => $actor->id,
            ];
            $alliance = $target instanceof Alliance ? $target : null;
            $this->audit->record('event.objective.assignment_removed', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('event.objective.assignment_removed', $alliance?->id, $locked, $metadata, partitionKey: $event->scope->value.':'.$target->id);
            $locked->delete();
        });
    }
}
