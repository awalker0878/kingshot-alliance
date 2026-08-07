<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Events\FormationComposition;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\RallyGuidanceRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRallyGuidanceRule
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    /** @param list<string> $heroRecommendations */
    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        FormationComposition $composition,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveUntil = null,
        array $heroRecommendations = [],
        ?string $leadRequirements = null,
        ?string $joinerGuidance = null,
        ?string $notes = null,
        ?string $source = null,
        ?string $rationale = null,
    ): RallyGuidanceRule {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage rally guidance.');
        }

        if ($effectiveUntil !== null && $effectiveUntil->lessThan($effectiveFrom)) {
            throw new InvalidArgumentException('Guidance end date cannot be earlier than its effective date.');
        }

        $heroes = array_values(array_filter(array_map(
            static fn (string $hero): string => trim($hero),
            $heroRecommendations,
        ), static fn (string $hero): bool => $hero !== ''));

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $name,
            $composition,
            $effectiveFrom,
            $effectiveUntil,
            $heroes,
            $leadRequirements,
            $joinerGuidance,
            $notes,
            $source,
            $rationale,
        ): RallyGuidanceRule {
            $rule = RallyGuidanceRule::query()->create([
                'alliance_id' => $alliance->id,
                'name' => trim($name),
                ...$composition->toArray(),
                'hero_recommendations' => $heroes === [] ? null : $heroes,
                'lead_requirements' => $leadRequirements === null ? null : trim($leadRequirements),
                'joiner_guidance' => $joinerGuidance === null ? null : trim($joinerGuidance),
                'notes' => $notes === null ? null : trim($notes),
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_until' => $effectiveUntil?->toDateString(),
                'source' => $source === null ? null : trim($source),
                'rationale' => $rationale === null ? null : trim($rationale),
                'is_active' => true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('rally.guidance.created', $actor, $rule, $alliance, [
                'effective_from' => $effectiveFrom->toDateString(),
                'source' => $rule->source,
            ]);
            $this->outbox->record('rally.guidance.created', $alliance, $rule, [
                'effective_from' => $effectiveFrom->toDateString(),
            ]);

            return $rule;
        });
    }
}
