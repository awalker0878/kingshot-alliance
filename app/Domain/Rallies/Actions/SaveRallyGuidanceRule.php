<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Models\RallyGuidanceRule;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGuidanceRule
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $heroes */
    public function handle(
        Player $actor,
        Alliance $alliance,
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
        ?RallyGuidanceRule $rule = null,
    ): RallyGuidanceRule {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventAllianceManage)) {
            throw new AuthorizationException;
        }
        if ($rule instanceof RallyGuidanceRule && (string) $rule->alliance_id !== (string) $alliance->id) {
            throw new AuthorizationException;
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Guidance name is required and must be 120 characters or fewer.']);
        }
        if ($effectiveFrom instanceof CarbonImmutable && $effectiveUntil instanceof CarbonImmutable && $effectiveUntil->lessThan($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_until' => 'Effective until must be on or after effective from.']);
        }
        $heroes = $this->normalizeHeroes($heroes);

        return DB::transaction(function () use ($actor, $alliance, $name, $composition, $heroes, $leadRequirements, $joinerGuidance, $source, $rationale, $effectiveFrom, $effectiveUntil, $isActive, $rule): RallyGuidanceRule {
            Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();
            $record = $rule instanceof RallyGuidanceRule
                ? RallyGuidanceRule::query()->whereKey($rule->id)->where('alliance_id', $alliance->id)->lockForUpdate()->firstOrFail()
                : new RallyGuidanceRule(['alliance_id' => $alliance->id]);
            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $actor->id;
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
                'updated_by_player_id' => $actor->id,
            ])->save();

            $event = $created ? 'rally.guidance.created' : 'rally.guidance.updated';
            $metadata = [
                'alliance_id' => (string) $alliance->id,
                'guidance_rule_id' => (string) $record->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($event, $actor, $record, $alliance, $metadata);
            $this->outbox->record($event, (string) $alliance->id, $record, $metadata);

            return $record->refresh();
        });
    }

    /** @param list<string> $heroes @return list<string> */
    private function normalizeHeroes(array $heroes): array
    {
        return array_values(array_slice(array_filter(
            array_map(static fn ($hero): string => trim((string) $hero), $heroes),
            static fn (string $hero): bool => $hero !== '',
        ), 0, 5));
    }

    private function text(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
