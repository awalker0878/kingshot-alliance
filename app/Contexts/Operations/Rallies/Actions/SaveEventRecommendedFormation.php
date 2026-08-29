<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\RallyGuidanceRule;
use App\Contexts\Operations\Rallies\Services\RallyWriteState;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventRecommendedFormation
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventWorkflowGuard $workflows,
        private RallyWriteState $rallyWriteState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $allianceId,
        string $key,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?RallyAssignmentRole $assignmentRole = null,
        ?string $guidanceRuleId = null,
        ?string $notes = null,
        int $sortOrder = 0,
        ?string $formationId = null,
    ): void {
        $key = Str::slug($key);
        $name = trim($name);
        if ($key === '' || mb_strlen($key) > 64) {
            throw ValidationException::withMessages(['key' => 'Formation key is required and must be 64 characters or fewer.']);
        }
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }
        $heroes = array_values(array_slice(array_filter(
            array_map(static fn ($hero): string => trim((string) $hero), $heroes),
            static fn (string $hero): bool => $hero !== '',
        ), 0, 5));

        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $allianceId, $key, $name, $composition, $heroes, $assignmentRole, $guidanceRuleId, $notes, $sortOrder, $formationId): void {
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

            $guidance = $guidanceRuleId === null ? null : RallyGuidanceRule::query()
                ->whereKey($guidanceRuleId)
                ->where('alliance_id', $alliance->allianceId)
                ->sharedLock()
                ->firstOrFail();

            $record = $formationId === null
                ? new EventRecommendedFormation([
                    'occurrence_id' => $occurrence->id,
                    'alliance_id' => $alliance->allianceId,
                ])
                : EventRecommendedFormation::query()
                    ->whereKey($formationId)
                    ->where('occurrence_id', $occurrence->id)
                    ->where('alliance_id', $alliance->allianceId)
                    ->lockForUpdate()
                    ->firstOrFail();

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->playerId;
            }
            $record->forceFill([
                'guidance_rule_id' => $guidance?->id,
                'key' => $key,
                'name' => $name,
                'assignment_role' => $assignmentRole?->value,
                ...$composition->toArray(),
                'heroes' => $heroes,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $eventName = $created ? 'rally.recommended_formation.created' : 'rally.recommended_formation.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => $alliance->allianceId,
                'formation_id' => (string) $record->id,
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
