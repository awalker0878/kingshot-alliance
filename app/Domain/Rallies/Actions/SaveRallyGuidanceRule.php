<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Rallies\Models\RallyGuidanceRule;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveRallyGuidanceRule
{
    public function __construct(
        private AllianceMutationAuthority $authority,
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
        if ($rule instanceof RallyGuidanceRule && (string) $rule->alliance_id !== (string) $alliance->id) {
            throw new AuthorizationException;
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Guidance name is required and must be 120 characters or fewer.']);
        }

        if ($effectiveFrom instanceof CarbonImmutable
            && $effectiveUntil instanceof CarbonImmutable
            && $effectiveUntil->lessThan($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_until' => 'Effective until must be on or after effective from.']);
        }

        $heroes = $this->normalizeHeroes($heroes);

        return DB::transaction(function () use ($actor, $alliance, $name, $composition, $heroes, $leadRequirements, $joinerGuidance, $source, $rationale, $effectiveFrom, $effectiveUntil, $isActive, $rule): RallyGuidanceRule {
            $context = $this->authority->require($actor, $alliance, PermissionKey::EventAllianceManage);

            $record = $rule instanceof RallyGuidanceRule
                ? RallyGuidanceRule::query()
                    ->whereKey($rule->id)
                    ->where('alliance_id', $context->alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new RallyGuidanceRule(['alliance_id' => $context->alliance->id]);

            $created = ! $record->exists;
            if ($created) {
                $record->created_by_player_id = $context->actor->id;
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
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            // The unique(alliance_id,name) constraint is the race-proof duplicate-name invariant.
            $event = $created ? 'rally.guidance.created' : 'rally.guidance.updated';
            $metadata = [
                'alliance_id' => (string) $context->alliance->id,
                'guidance_rule_id' => (string) $record->id,
                'actor_player_id' => $context->actor->id,
            ];
            $this->audit->record($event, $context->actor, $record, $context->alliance, $metadata);
            $this->outbox->record($event, (string) $context->alliance->id, $record, $metadata);

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
