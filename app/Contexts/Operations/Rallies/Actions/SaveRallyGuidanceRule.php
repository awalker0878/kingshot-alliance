<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Rallies\Models\RallyGuidanceRule;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGuidanceRule
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceReferenceQuery $alliances,
        private AllianceAuthorityFactsQuery $authorityFacts,
        private AllianceOperationsAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $name,
        FormationComposition $composition,
        array $heroes = [],
        ?string $leadRequirements = null,
        ?string $joinerGuidance = null,
        ?string $source = null,
        ?string $rationale = null,
        ?CarbonImmutable $effectiveFrom = null,
        ?CarbonImmutable $effectiveUntil = null,
        bool $isActive = true,
        ?string $ruleId = null,
    ): void {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Guidance name is required and must be 120 characters or fewer.']);
        }
        if ($effectiveFrom instanceof CarbonImmutable && $effectiveUntil instanceof CarbonImmutable && $effectiveUntil->lessThan($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_until' => 'Effective until must be on or after effective from.']);
        }
        $heroes = $this->normalizeHeroes($heroes);

        DB::transaction(function () use ($actorPlayerId, $allianceId, $name, $composition, $heroes, $leadRequirements, $joinerGuidance, $source, $rationale, $effectiveFrom, $effectiveUntil, $isActive, $ruleId): void {
            $actor = $this->players->lockCurrent($actorPlayerId);
            $alliance = $this->alliances->lockCurrent($allianceId);
            $facts = $this->authorityFacts->lockCurrent($actorPlayerId, $allianceId);
            if ($facts === null || ! $this->authority->allowsFacts($facts, OperationsPermission::EventAllianceManage)) {
                throw new AuthorizationException;
            }

            $record = $ruleId !== null
                ? RallyGuidanceRule::query()->whereKey($ruleId)->where('alliance_id', $allianceId)->lockForUpdate()->firstOrFail()
                : new RallyGuidanceRule(['alliance_id' => $allianceId]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actorPlayerId;
            }
            $record->forceFill([
                'name' => $name,
                ...$composition->toArray(),
                'hero_recommendations' => $heroes,
                'lead_requirements' => $this->text($leadRequirements),
                'joiner_guidance' => $this->text($joinerGuidance),
                'source' => $this->text($source),
                'rationale' => $this->text($rationale),
                'effective_from' => $effectiveFrom?->toDateString(),
                'effective_until' => $effectiveUntil?->toDateString(),
                'is_active' => $isActive,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $eventName = $created ? 'rally.guidance.created' : 'rally.guidance.updated';
            $metadata = ['alliance_id' => $allianceId, 'guidance_rule_id' => (string) $record->id, 'actor_player_id' => $actorPlayerId];
            $this->audit->record($eventName, $actor, $record, $allianceId, $metadata);
            $this->outbox->record($eventName, $allianceId, $record, $metadata, partitionKey: 'alliance:'.$allianceId);
        });
    }

    /**
     * @param  list<string>  $heroes
     * @return list<string>
     */
    private function normalizeHeroes(array $heroes): array
    {
        return array_values(array_slice(array_filter(array_map(static fn ($hero): string => trim((string) $hero), $heroes), static fn (string $hero): bool => $hero !== ''), 0, 5));
    }

    private function text(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
