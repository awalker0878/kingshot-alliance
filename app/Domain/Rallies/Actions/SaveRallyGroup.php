<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Models\EventRecommendedFormation;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Rallies\Services\RallyAllianceResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGroup
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private RallyAllianceResolver $alliances,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        string $allianceId,
        string $name,
        ?int $maxJoiners = null,
        ?EventRecommendedFormation $recommendedFormation = null,
        ?string $notes = null,
        int $sortOrder = 0,
        ?RallyGroup $group = null,
    ): RallyGroup {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::RallyGuidance);
        $this->authorization->authorizeManager($actor, $event);
        $alliance = $this->alliances->resolve($event, $allianceId);
        if ($recommendedFormation instanceof EventRecommendedFormation
            && ((string) $recommendedFormation->occurrence_id !== (string) $occurrence->id || (string) $recommendedFormation->alliance_id !== (string) $alliance->id)) {
            throw new AuthorizationException;
        }
        if ($group instanceof RallyGroup
            && ((string) $group->occurrence_id !== (string) $occurrence->id || (string) $group->alliance_id !== (string) $alliance->id)) {
            throw new AuthorizationException;
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Rally group name is required and must be 120 characters or fewer.']);
        }
        if ($maxJoiners !== null && $maxJoiners < 1) {
            throw ValidationException::withMessages(['max_joiners' => 'Maximum joiners must be at least one.']);
        }
        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $alliance, $name, $maxJoiners, $recommendedFormation, $notes, $sortOrder, $group, $target): RallyGroup {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $record = $group instanceof RallyGroup
                ? RallyGroup::query()->whereKey($group->id)->where('occurrence_id', $occurrence->id)->where('alliance_id', $alliance->id)->lockForUpdate()->firstOrFail()
                : new RallyGroup(['occurrence_id' => $occurrence->id, 'alliance_id' => $alliance->id]);
            if ($record->exists && $maxJoiners !== null) {
                $activeJoiners = $record->assignments()
                    ->where('role', \App\Domain\Rallies\Enums\RallyAssignmentRole::Joiner->value)
                    ->whereNotIn('status', [\App\Domain\Rallies\Enums\RallyAssignmentStatus::Declined->value, \App\Domain\Rallies\Enums\RallyAssignmentStatus::Removed->value])
                    ->count();
                if ($activeJoiners > $maxJoiners) {
                    throw ValidationException::withMessages(['max_joiners' => 'Maximum joiners cannot be lower than the current active joiner count.']);
                }
            }
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->id;
            }
            $record->forceFill([
                'recommended_formation_id' => $recommendedFormation?->id,
                'name' => $name,
                'max_joiners' => $maxJoiners,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
                'updated_by_player_id' => $actor->id,
            ])->save();

            $eventName = $created ? 'rally.group.created' : 'rally.group.updated';
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => (string) $alliance->id,
                'rally_group_id' => (string) $record->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, (string) $alliance->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }
}
