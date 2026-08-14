<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Models\EventRecommendedFormation;
use App\Domain\Rallies\Models\RallyGuidanceRule;
use App\Domain\Rallies\Services\RallyAllianceResolver;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventRecommendedFormation
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private RallyAllianceResolver $alliances,
        private EventTargetResolver $targets,
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
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Formations);
        $this->authorization->authorizeManager($actor, $event);
        $alliance = $this->alliances->resolve($event, $allianceId);
        if ($guidance instanceof RallyGuidanceRule && (string) $guidance->alliance_id !== (string) $alliance->id) {
            throw new AuthorizationException;
        }
        if ($formation instanceof EventRecommendedFormation
            && ((string) $formation->occurrence_id !== (string) $occurrence->id || (string) $formation->alliance_id !== (string) $alliance->id)) {
            throw new AuthorizationException;
        }

        $key = Str::slug($key);
        $name = trim($name);
        if ($key === '' || mb_strlen($key) > 64) {
            throw ValidationException::withMessages(['key' => 'Formation key is required and must be 64 characters or fewer.']);
        }
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Formation name is required and must be 120 characters or fewer.']);
        }
        $heroes = array_values(array_slice(array_filter(array_map(static fn ($hero): string => trim((string) $hero), $heroes), static fn (string $hero): bool => $hero !== ''), 0, 5));
        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $alliance, $key, $name, $composition, $heroes, $assignmentRole, $guidance, $notes, $sortOrder, $formation, $target): EventRecommendedFormation {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $record = $formation instanceof EventRecommendedFormation
                ? EventRecommendedFormation::query()->whereKey($formation->id)->where('occurrence_id', $occurrence->id)->where('alliance_id', $alliance->id)->lockForUpdate()->firstOrFail()
                : new EventRecommendedFormation(['occurrence_id' => $occurrence->id, 'alliance_id' => $alliance->id]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->id;
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
                'updated_by_player_id' => $actor->id,
            ])->save();

            $eventName = $created ? 'rally.recommended_formation.created' : 'rally.recommended_formation.updated';
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => (string) $alliance->id,
                'formation_id' => (string) $record->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, (string) $alliance->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }
}
