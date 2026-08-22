<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateTerritoryPlanAlliances
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private AllianceReferenceQuery $alliances,
        private AuditRecorder $audit,
    ) {}

    /** @param list<array<string, mixed>> $layers */
    public function handle(
        string $actorPlayerId,
        string $planId,
        int $expectedRevision,
        array $layers,
    ): TerritoryPlanMutationReceipt {
        $normalized = $this->normalize($layers);

        return DB::transaction(function () use (
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $normalized,
        ): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            if ($context->plan->scope !== TerritoryPlanScope::Kingdom) {
                throw ValidationException::withMessages([
                    'alliances' => 'Alliance layers can only be managed independently on a Kingdom territory plan.',
                ]);
            }

            if ((int) $context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan changed before its Alliance layers could be updated. Reload the current layout first.',
                ]);
            }

            foreach ($normalized as $layer) {
                $allianceId = $layer['alliance_id'];
                if (! is_string($allianceId)) {
                    continue;
                }

                $reference = $this->alliances->lockCurrent($allianceId);
                if ($reference->kingdomId !== (string) $context->plan->kingdom_id) {
                    throw ValidationException::withMessages([
                        'alliances' => 'Linked Alliances must belong to the plan Kingdom.',
                    ]);
                }
            }

            $existing = TerritoryPlanAlliance::query()
                ->where('territory_plan_id', $planId)
                ->lockForUpdate()
                ->get()
                ->keyBy('plan_key');
            $retainedKeys = [];
            $added = 0;
            $updated = 0;

            foreach ($normalized as $layer) {
                $key = $layer['key'];
                $retainedKeys[$key] = true;
                $row = $existing->get($key);

                if (! $row instanceof TerritoryPlanAlliance) {
                    TerritoryPlanAlliance::query()->create([
                        'territory_plan_id' => $planId,
                        'plan_key' => $key,
                        ...$this->persistenceAttributes($layer),
                    ]);
                    $added++;

                    continue;
                }

                $identityChanged = (string) ($row->alliance_id ?? '') !== (string) ($layer['alliance_id'] ?? '')
                    || (string) ($row->external_name ?? '') !== (string) ($layer['external_name'] ?? '');
                $ownsObjects = TerritoryPlanObject::query()
                    ->where('territory_plan_alliance_id', $row->id)
                    ->exists();

                if ($identityChanged && $ownsObjects) {
                    throw ValidationException::withMessages([
                        'alliances' => 'Remove planned objects from an Alliance layer before changing that layer identity.',
                    ]);
                }

                $attributes = $this->persistenceAttributes($layer);
                if ($row->only(array_keys($attributes)) === $attributes) {
                    continue;
                }

                $row->forceFill($attributes)->save();
                $updated++;
            }

            $removed = 0;
            foreach ($existing as $key => $row) {
                if (isset($retainedKeys[$key]) || ! $row instanceof TerritoryPlanAlliance) {
                    continue;
                }

                if (
                    TerritoryPlanObject::query()
                        ->where('territory_plan_alliance_id', $row->id)
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'alliances' => 'Remove planned objects from an Alliance layer before removing that layer.',
                    ]);
                }

                $row->delete();
                $removed++;
            }

            $preferences = $context->plan->planning_preferences ?? [];
            $selectedTraps = $preferences['selected_bear_trap_by_alliance'] ?? null;
            if (is_array($selectedTraps)) {
                $preferences['selected_bear_trap_by_alliance'] = array_intersect_key(
                    $selectedTraps,
                    $retainedKeys,
                );

                if ($preferences['selected_bear_trap_by_alliance'] === []) {
                    unset($preferences['selected_bear_trap_by_alliance']);
                }
            }

            $nextRevision = $expectedRevision + 1;
            $context->plan->forceFill([
                'planning_preferences' => $preferences,
                'revision' => $nextRevision,
                'status' => TerritoryPlanStatus::Draft,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $this->audit->record(
                'territory.plan.alliances_updated',
                $context->actor,
                $context->plan,
                null,
                [
                    'revision' => $nextRevision,
                    'alliance_count' => count($normalized),
                    'added' => $added,
                    'updated' => $updated,
                    'removed' => $removed,
                ],
            );

            return new TerritoryPlanMutationReceipt(
                $planId,
                $nextRevision,
                TerritoryPlanStatus::Draft->value,
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @return list<array{key: string, alliance_id: ?string, external_name: ?string, external_tag: ?string, display_name: string, presentation_color: string, sort_order: int, visible: bool, locked: bool}>
     */
    private function normalize(array $layers): array
    {
        if ($layers === [] || count($layers) > 50) {
            throw ValidationException::withMessages([
                'alliances' => 'A Kingdom plan requires between 1 and 50 Alliance layers.',
            ]);
        }

        $keys = [];
        $linked = [];
        $normalized = [];

        foreach ($layers as $index => $layer) {
            $key = $this->stringOrNull($layer['key'] ?? null);
            $allianceId = $this->stringOrNull($layer['alliance_id'] ?? null);
            $externalName = $this->stringOrNull($layer['external_name'] ?? null);
            $externalTag = $this->stringOrNull($layer['external_tag'] ?? null);
            $displayName = $this->stringOrNull($layer['display_name'] ?? null);
            $color = strtolower(trim((string) ($layer['presentation_color'] ?? '#4da3ff')));

            if (
                $key === null
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || $displayName === null
                || mb_strlen($displayName) > 160
                || ($allianceId === null) === ($externalName === null)
                || ($externalName !== null && mb_strlen($externalName) > 160)
                || ($externalTag !== null && mb_strlen($externalTag) > 32)
                || ! preg_match('/^#[0-9a-f]{6}$/', $color)
            ) {
                throw ValidationException::withMessages([
                    'alliances' => 'Every Alliance layer requires unique identity, one linked or external Alliance, a display name, and a valid map color.',
                ]);
            }

            if ($allianceId !== null && isset($linked[$allianceId])) {
                throw ValidationException::withMessages([
                    'alliances' => 'A linked Alliance can appear only once in a Kingdom plan.',
                ]);
            }

            $keys[$key] = true;
            if ($allianceId !== null) {
                $linked[$allianceId] = true;
            }

            $normalized[] = [
                'key' => $key,
                'alliance_id' => $allianceId,
                'external_name' => $externalName,
                'external_tag' => $externalTag,
                'display_name' => $displayName,
                'presentation_color' => $color,
                'sort_order' => $index,
                'visible' => (bool) ($layer['visible'] ?? true),
                'locked' => (bool) ($layer['locked'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{key: string, alliance_id: ?string, external_name: ?string, external_tag: ?string, display_name: string, presentation_color: string, sort_order: int, visible: bool, locked: bool}  $layer
     * @return array{alliance_id: ?string, external_name: ?string, external_tag: ?string, display_name: string, presentation_color: string, sort_order: int, visible: bool, locked: bool}
     */
    private function persistenceAttributes(array $layer): array
    {
        return [
            'alliance_id' => $layer['alliance_id'],
            'external_name' => $layer['external_name'],
            'external_tag' => $layer['external_tag'],
            'display_name' => $layer['display_name'],
            'presentation_color' => $layer['presentation_color'],
            'sort_order' => $layer['sort_order'],
            'visible' => $layer['visible'],
            'locked' => $layer['locked'],
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
