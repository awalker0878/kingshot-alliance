<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventCapabilityGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\RallyGuidanceRule;
use App\Contexts\Operations\Rallies\Services\RallyAllianceResolver;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventRecommendedFormation
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private RallyAllianceResolver $alliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        string $allianceId,
        string $key,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?RallyAssignmentRole $assignmentRole = null,
        ?RallyGuidanceRule $guidance = null,
        ?string $notes = null,
        int $sortOrder = 0,
        ?EventRecommendedFormation $formation = null,
    ): EventRecommendedFormation {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

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

        return DB::transaction(function () use ($actor, $occurrence, $event, $allianceId, $key, $name, $composition, $heroes, $assignmentRole, $guidance, $notes, $sortOrder, $formation): EventRecommendedFormation {
            $context = $this->eventWriteState->lockEventScope($actor, $event);
            $this->eventAuthority->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::Formations);

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

            $lockedGuidance = null;
            if ($guidance instanceof RallyGuidanceRule) {
                if ((string) $guidance->alliance_id !== (string) $alliance->id) {
                    throw new AuthorizationException;
                }

                $lockedGuidance = RallyGuidanceRule::query()
                    ->whereKey($guidance->id)
                    ->where('alliance_id', $alliance->id)
                    ->sharedLock()
                    ->firstOrFail();
            }

            if ($formation instanceof EventRecommendedFormation
                && ((string) $formation->occurrence_id !== (string) $lockedOccurrence->id
                    || (string) $formation->alliance_id !== (string) $alliance->id)) {
                throw new AuthorizationException;
            }

            $record = $formation instanceof EventRecommendedFormation
                ? EventRecommendedFormation::query()
                    ->whereKey($formation->id)
                    ->where('occurrence_id', $lockedOccurrence->id)
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new EventRecommendedFormation([
                    'occurrence_id' => $lockedOccurrence->id,
                    'alliance_id' => $alliance->id,
                ]);

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->id;
            }
            $record->forceFill([
                'guidance_rule_id' => $lockedGuidance?->id,
                'key' => $key,
                'name' => $name,
                'assignment_role' => $assignmentRole?->value,
                ...$composition->toArray(),
                'heroes' => $heroes,
                'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            // unique(occurrence_id, alliance_id, key) remains the race-proof key invariant.
            $eventName = $created ? 'rally.recommended_formation.created' : 'rally.recommended_formation.updated';
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'alliance_id' => (string) $alliance->id,
                'formation_id' => (string) $record->id,
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
