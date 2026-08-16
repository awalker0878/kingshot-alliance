<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyAllianceResolver;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGroup
{
    public function __construct(
        private EventAuthorization $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private RallyAllianceResolver $alliances,
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
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        if ($recommendedFormation instanceof EventRecommendedFormation
            && ((string) $recommendedFormation->occurrence_id !== (string) $occurrence->id
                || (string) $recommendedFormation->alliance_id !== $allianceId)) {
            throw new AuthorizationException;
        }

        if ($group instanceof RallyGroup
            && ((string) $group->occurrence_id !== (string) $occurrence->id
                || (string) $group->alliance_id !== $allianceId)) {
            throw new AuthorizationException;
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Rally group name is required and must be 120 characters or fewer.']);
        }

        if ($maxJoiners !== null && $maxJoiners < 1) {
            throw ValidationException::withMessages(['max_joiners' => 'Maximum joiners must be at least one.']);
        }

        return DB::transaction(function () use ($actor, $occurrence, $event, $allianceId, $name, $maxJoiners, $recommendedFormation, $notes, $sortOrder, $group): RallyGroup {
            $context = $this->eventAuthority->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            // Group metadata is not occurrence-wide occupancy state. Share-lock the
            // occurrence lifecycle; assignment/move workflows take it exclusively.
            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $resolvedAlliance = $this->alliances->resolve($context->event, $allianceId);
            $alliance = Alliance::query()
                ->whereKey($resolvedAlliance->id)
                ->where('status', AllianceStatus::Active->value)
                ->sharedLock()
                ->firstOrFail();

            $formation = null;
            if ($recommendedFormation instanceof EventRecommendedFormation) {
                $formation = EventRecommendedFormation::query()
                    ->whereKey($recommendedFormation->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('alliance_id', $alliance->id)
                    ->sharedLock()
                    ->firstOrFail();
            }

            $record = $group instanceof RallyGroup
                ? RallyGroup::query()
                    ->whereKey($group->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new RallyGroup([
                    'occurrence_id' => $lockedOccurrence->id,
                    'alliance_id' => $alliance->id,
                ]);

            if ($record->exists && $maxJoiners !== null) {
                // AssignRallyPlayer locks this group exclusively before occupancy
                // changes, so the active joiner count is stable while max is reduced.
                $activeJoiners = $record->assignments()
                    ->where('role', RallyAssignmentRole::Joiner->value)
                    ->whereNotIn('status', [
                        RallyAssignmentStatus::Declined->value,
                        RallyAssignmentStatus::Removed->value,
                    ])
                    ->count();

                if ($activeJoiners > $maxJoiners) {
                    throw ValidationException::withMessages([
                        'max_joiners' => 'Maximum joiners cannot be lower than the current active joiner count.',
                    ]);
                }
            }

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->id;
            }

            $record->forceFill([
                'recommended_formation_id' => $formation?->id,
                'name' => $name,
                'max_joiners' => $maxJoiners,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $eventName = $created ? 'rally.group.created' : 'rally.group.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'alliance_id' => (string) $alliance->id,
                'rally_group_id' => (string) $record->id,
                'actor_player_id' => $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                (string) $alliance->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $record->refresh();
        });
    }
}
