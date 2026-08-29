<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGroup
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventWorkflowGuard $workflows,
        private RallyWriteState $rallyWriteState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $allianceId,
        string $name,
        ?int $maxJoiners = null,
        ?string $recommendedFormationId = null,
        ?string $notes = null,
        int $sortOrder = 0,
        ?string $groupId = null,
    ): void {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Rally group name is required and must be 120 characters or fewer.']);
        }
        if ($maxJoiners !== null && $maxJoiners < 1) {
            throw ValidationException::withMessages(['max_joiners' => 'Maximum joiners must be at least one.']);
        }

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $allianceId, $name, $maxJoiners, $recommendedFormationId, $notes, $sortOrder, $groupId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->eventAuthority->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Rallies);

            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();
            $alliance = $this->rallyWriteState->lockAllianceForTarget($context->target, $allianceId);

            $formation = $recommendedFormationId === null ? null : EventRecommendedFormation::query()
                ->whereKey($recommendedFormationId)
                ->where('occurrence_id', $occurrence->id)
                ->where('alliance_id', $alliance->allianceId)
                ->sharedLock()
                ->firstOrFail();

            $record = $groupId === null
                ? new RallyGroup([
                    'occurrence_id' => $occurrence->id,
                    'alliance_id' => $alliance->allianceId,
                ])
                : RallyGroup::query()
                    ->whereKey($groupId)
                    ->where('occurrence_id', $occurrence->id)
                    ->where('alliance_id', $alliance->allianceId)
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($record->exists && $maxJoiners !== null) {
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
                $record->created_by_player_id = $context->actor->playerId;
            }
            $record->forceFill([
                'recommended_formation_id' => $formation?->id,
                'name' => $name,
                'max_joiners' => $maxJoiners,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $eventName = $created ? 'rally.group.created' : 'rally.group.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'rally_group_id' => (string) $record->id,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record($eventName, $context->actor, $record, $alliance->allianceId, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance->allianceId,
                $record,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
