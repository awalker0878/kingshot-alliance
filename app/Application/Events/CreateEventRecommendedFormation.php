<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Events\Enums\RallyAssignmentRole;
use App\Domain\Events\FormationComposition;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\EventOccurrence;
use App\Models\EventRecommendedFormation;
use App\Models\RallyGuidanceRule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CreateEventRecommendedFormation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        User $actor,
        Alliance $alliance,
        EventOccurrence $occurrence,
        string $name,
        RallyAssignmentRole $assignmentRole,
        FormationComposition $composition,
        array $heroes = [],
        ?RallyGuidanceRule $guidanceRule = null,
        ?string $notes = null,
        int $sortOrder = 0,
    ): EventRecommendedFormation {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage event formations.');
        }

        if ($occurrence->alliance_id !== $alliance->id
            || ($guidanceRule !== null && $guidanceRule->alliance_id !== $alliance->id)) {
            throw new AuthorizationException('The formation references another alliance.');
        }

        $heroList = array_values(array_filter(array_map(
            static fn (string $hero): string => trim($hero),
            $heroes,
        ), static fn (string $hero): bool => $hero !== ''));

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $occurrence,
            $name,
            $assignmentRole,
            $composition,
            $heroList,
            $guidanceRule,
            $notes,
            $sortOrder,
        ): EventRecommendedFormation {
            $formation = EventRecommendedFormation::query()->create([
                'alliance_id' => $alliance->id,
                'occurrence_id' => $occurrence->id,
                'guidance_rule_id' => $guidanceRule?->id,
                'name' => trim($name),
                'assignment_role' => $assignmentRole->value,
                'heroes' => $heroList === [] ? null : $heroList,
                ...$composition->toArray(),
                'notes' => $notes === null ? null : trim($notes),
                'sort_order' => max(0, $sortOrder),
            ]);

            $this->audit->record('event.formation.created', $actor, $formation, $alliance, [
                'occurrence_id' => $occurrence->id,
                'assignment_role' => $assignmentRole->value,
            ]);
            $this->outbox->record('event.formation.created', $alliance, $formation, [
                'occurrence_id' => $occurrence->id,
                'assignment_role' => $assignmentRole->value,
            ]);

            return $formation;
        });
    }
}
