<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryObjectType;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanGroup;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ValueError;

final readonly class SaveTerritoryPlan
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private AllianceReferenceQuery $alliances,
        private PlayerReferenceQuery $players,
        private PlayerMembershipQuery $memberships,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $objects
     * @param  array<string, mixed>  $preferences
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        int $expectedRevision,
        array $alliances,
        array $groups,
        array $objects,
        array $preferences = [],
    ): TerritoryPlanMutationReceipt {
        $normalizedAlliances = $this->normalizeAlliances($alliances);
        $normalizedGroups = $this->normalizeGroups($groups);
        $normalizedObjects = $this->normalizeObjects(
            $objects,
            $normalizedAlliances,
            $normalizedGroups,
        );
        $preferences = $this->normalizePreferences(
            $preferences,
            $normalizedAlliances,
            $normalizedObjects,
        );

        return DB::transaction(function () use (
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $normalizedAlliances,
            $normalizedGroups,
            $normalizedObjects,
            $preferences,
        ): TerritoryPlanMutationReceipt {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            if ($context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan was changed by another editor. Reload it before saving.',
                ]);
            }

            $this->validateLinkedAlliances($context->plan, $normalizedAlliances);
            $this->validateGovernorIdentities(
                $context->plan,
                $normalizedAlliances,
                $normalizedObjects,
            );

            $dataset = $this->datasets->require(
                $context->plan->map_dataset_id,
                $context->plan->map_dataset_checksum,
            );
            $validationObjects = array_map(
                static fn (array $object): array => [
                    'key' => $object['key'],
                    'type' => $object['type'],
                    'x' => $object['x'],
                    'y' => $object['y'],
                    'alliance_key' => $object['alliance_key'],
                ],
                $normalizedObjects,
            );
            $validation = $this->placement->validate(
                $dataset,
                $validationObjects,
                $preferences,
            );

            if (! $validation->valid()) {
                throw ValidationException::withMessages([
                    'layout' => [json_encode($validation->toArray(), JSON_THROW_ON_ERROR)],
                ]);
            }

            TerritoryPlanObject::query()->where('territory_plan_id', $planId)->delete();
            TerritoryPlanGroup::query()->where('territory_plan_id', $planId)->delete();
            TerritoryPlanAlliance::query()->where('territory_plan_id', $planId)->delete();

            $allianceIds = [];
            foreach ($normalizedAlliances as $alliance) {
                $row = TerritoryPlanAlliance::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $alliance['key'],
                    'alliance_id' => $alliance['alliance_id'],
                    'external_name' => $alliance['external_name'],
                    'external_tag' => $alliance['external_tag'],
                    'display_name' => $alliance['display_name'],
                    'presentation_color' => $alliance['presentation_color'],
                    'sort_order' => $alliance['sort_order'],
                    'visible' => $alliance['visible'],
                    'locked' => $alliance['locked'],
                ]);
                $allianceIds[$alliance['key']] = (string) $row->id;
            }

            $groupIds = [];
            foreach ($normalizedGroups as $group) {
                $row = TerritoryPlanGroup::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $group['key'],
                    'label' => $group['label'],
                ]);
                $groupIds[$group['key']] = (string) $row->id;
            }

            foreach ($normalizedObjects as $object) {
                TerritoryPlanObject::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $object['key'],
                    'territory_plan_alliance_id' => $allianceIds[$object['alliance_key']],
                    'group_id' => $object['group_key'] === null
                        ? null
                        : $groupIds[$object['group_key']],
                    'object_type' => TerritoryObjectType::from($object['type']),
                    'player_id' => $object['player_id'],
                    'external_player_name' => $object['external_player_name'],
                    'label' => $object['label'],
                    'coordinate_x' => $object['x'],
                    'coordinate_y' => $object['y'],
                    'rotation' => $object['rotation'],
                    'sort_order' => $object['sort_order'],
                    'metadata' => $object['metadata'],
                ]);
            }

            $context->plan->forceFill([
                'planning_preferences' => $preferences,
                'revision' => $expectedRevision + 1,
                'status' => TerritoryPlanStatus::Draft,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $this->audit->record(
                'territory.plan.saved',
                $context->actor,
                $context->plan,
                $context->plan->owner_alliance_id,
                [
                    'revision' => $expectedRevision + 1,
                    'alliance_count' => count($normalizedAlliances),
                    'object_count' => count($normalizedObjects),
                    'warning_count' => count($validation->warnings),
                    'suggestion_count' => count($validation->suggestions),
                ],
            );

            return new TerritoryPlanMutationReceipt(
                $planId,
                $expectedRevision + 1,
                TerritoryPlanStatus::Draft->value,
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeAlliances(array $items): array
    {
        if ($items === [] || count($items) > 50) {
            throw ValidationException::withMessages([
                'alliances' => 'A plan requires between 1 and 50 Alliance layers.',
            ]);
        }

        $keys = [];
        $linkedAllianceIds = [];
        $result = [];

        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $allianceId = $this->nullableString($item['alliance_id'] ?? null);
            $externalName = $this->nullableString($item['external_name'] ?? null);
            $externalTag = $this->nullableString($item['external_tag'] ?? null);
            $displayName = trim((string) ($item['display_name'] ?? $externalName ?? ''));

            if (
                $key === ''
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || $displayName === ''
                || mb_strlen($displayName) > 160
                || ($externalName !== null && mb_strlen($externalName) > 160)
                || ($externalTag !== null && mb_strlen($externalTag) > 32)
                || ($allianceId === null && $externalName === null)
                || ($allianceId !== null && $externalName !== null)
            ) {
                throw ValidationException::withMessages([
                    'alliances' => 'Every Alliance layer needs a unique valid key, display name, and exactly one linked or external identity.',
                ]);
            }

            if ($allianceId !== null && isset($linkedAllianceIds[$allianceId])) {
                throw ValidationException::withMessages([
                    'alliances' => 'A linked Alliance can appear only once in a plan.',
                ]);
            }

            $keys[$key] = true;
            if ($allianceId !== null) {
                $linkedAllianceIds[$allianceId] = true;
            }

            $color = strtolower(trim((string) ($item['presentation_color'] ?? '#4da3ff')));
            if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
                throw ValidationException::withMessages([
                    'alliances' => 'Alliance presentation colors must use #RRGGBB.',
                ]);
            }

            $result[] = [
                'key' => $key,
                'alliance_id' => $allianceId,
                'external_name' => $externalName,
                'external_tag' => $externalTag,
                'display_name' => $displayName,
                'presentation_color' => $color,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'visible' => (bool) ($item['visible'] ?? true),
                'locked' => (bool) ($item['locked'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{key: string, label: ?string}>
     */
    private function normalizeGroups(array $items): array
    {
        if (count($items) > 500) {
            throw ValidationException::withMessages([
                'groups' => 'A plan may contain at most 500 groups.',
            ]);
        }

        $keys = [];
        $result = [];

        foreach ($items as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '' || mb_strlen($key) > 120 || isset($keys[$key])) {
                throw ValidationException::withMessages([
                    'groups' => 'Every group requires a unique valid key.',
                ]);
            }

            $keys[$key] = true;
            $label = $this->nullableString($item['label'] ?? null);
            if ($label !== null && mb_strlen($label) > 160) {
                throw ValidationException::withMessages([
                    'groups' => 'Group labels must be 160 characters or fewer.',
                ]);
            }

            $result[] = ['key' => $key, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array{key: string, label: ?string}>  $groups
     * @return list<array<string, mixed>>
     */
    private function normalizeObjects(array $items, array $alliances, array $groups): array
    {
        if (count($items) > 5000) {
            throw ValidationException::withMessages([
                'objects' => 'A plan may contain at most 5000 planned objects.',
            ]);
        }

        $allianceKeys = array_fill_keys(array_column($alliances, 'key'), true);
        $groupKeys = array_fill_keys(array_column($groups, 'key'), true);
        $keys = [];
        $result = [];

        foreach ($items as $index => $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $allianceKey = trim((string) ($item['alliance_key'] ?? ''));
            $groupKey = $this->nullableString($item['group_key'] ?? null);
            $type = trim((string) ($item['type'] ?? ''));

            if (
                $key === ''
                || mb_strlen($key) > 120
                || isset($keys[$key])
                || ! isset($allianceKeys[$allianceKey])
                || ($groupKey !== null && ! isset($groupKeys[$groupKey]))
            ) {
                throw ValidationException::withMessages([
                    'objects' => 'Every object needs unique valid identity and valid Alliance/group references.',
                ]);
            }

            $keys[$key] = true;
            try {
                TerritoryObjectType::from($type);
            } catch (ValueError) {
                throw ValidationException::withMessages([
                    'objects' => 'A planned object uses an unsupported type.',
                ]);
            }

            $rotation = (int) ($item['rotation'] ?? 0);
            if (! in_array($rotation, [0, 90, 180, 270], true)) {
                throw ValidationException::withMessages([
                    'objects' => 'Object rotation must be 0, 90, 180, or 270 degrees.',
                ]);
            }

            $playerId = $this->nullableString($item['player_id'] ?? null);
            $externalPlayerName = $this->nullableString($item['external_player_name'] ?? null);
            $label = $this->nullableString($item['label'] ?? null);
            if ($externalPlayerName !== null && mb_strlen($externalPlayerName) > 160) {
                throw ValidationException::withMessages([
                    'objects' => 'External Governor names must be 160 characters or fewer.',
                ]);
            }
            if ($label !== null && mb_strlen($label) > 160) {
                throw ValidationException::withMessages([
                    'objects' => 'Object labels must be 160 characters or fewer.',
                ]);
            }
            if (
                $type !== TerritoryObjectType::GovernorCity->value
                && ($playerId !== null || $externalPlayerName !== null)
            ) {
                throw ValidationException::withMessages([
                    'objects' => 'Only Governor cities may carry Governor identity.',
                ]);
            }
            if ($playerId !== null && $externalPlayerName !== null) {
                throw ValidationException::withMessages([
                    'objects' => 'A Governor city must use either a linked Governor or an external Governor label, not both.',
                ]);
            }

            $result[] = [
                'key' => $key,
                'alliance_key' => $allianceKey,
                'group_key' => $groupKey,
                'type' => $type,
                'player_id' => $playerId,
                'external_player_name' => $externalPlayerName,
                'label' => $label,
                'x' => (int) ($item['x'] ?? -1),
                'y' => (int) ($item['y'] ?? -1),
                'rotation' => $rotation,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $objects
     * @return array<string, mixed>
     */
    private function normalizePreferences(array $preferences, array $alliances, array $objects): array
    {
        $allowed = [];

        if (
            isset($preferences['preferred_bear_radius_tiles'])
            && $preferences['preferred_bear_radius_tiles'] !== ''
        ) {
            $radius = (float) $preferences['preferred_bear_radius_tiles'];
            if ($radius <= 0 || $radius > 1200) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'Preferred Bear radius must be between 0 and 1200 tiles.',
                ]);
            }
            $allowed['preferred_bear_radius_tiles'] = $radius;
        }

        if (
            isset($preferences['march_seconds_per_tile'])
            && $preferences['march_seconds_per_tile'] !== ''
        ) {
            $seconds = (float) $preferences['march_seconds_per_tile'];
            if ($seconds <= 0 || $seconds > 60) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'March-time planning assumption must be between 0 and 60 seconds per tile.',
                ]);
            }
            $allowed['march_seconds_per_tile'] = $seconds;
        }

        $selection = $preferences['selected_bear_trap_by_alliance'] ?? null;
        if ($selection !== null) {
            if (! is_array($selection)) {
                throw ValidationException::withMessages([
                    'planning_preferences' => 'Selected Bear Traps must be keyed by Alliance layer.',
                ]);
            }

            $allianceKeys = array_fill_keys(array_column($alliances, 'key'), true);
            $trapKeysByAlliance = [];
            foreach ($objects as $object) {
                if ($object['type'] === TerritoryObjectType::BearTrap->value) {
                    $trapKeysByAlliance[$object['alliance_key']][$object['key']] = true;
                }
            }

            $normalizedSelection = [];
            foreach ($selection as $allianceKey => $trapKey) {
                if (
                    ! is_string($allianceKey)
                    || ! is_string($trapKey)
                    || ! isset($allianceKeys[$allianceKey])
                    || ! isset($trapKeysByAlliance[$allianceKey][$trapKey])
                ) {
                    throw ValidationException::withMessages([
                        'planning_preferences' => 'A selected Bear Trap must belong to the selected Alliance layer.',
                    ]);
                }
                $normalizedSelection[$allianceKey] = $trapKey;
            }
            ksort($normalizedSelection);
            if ($normalizedSelection !== []) {
                $allowed['selected_bear_trap_by_alliance'] = $normalizedSelection;
            }
        }

        return $allowed;
    }

    /** @param list<array<string, mixed>> $planAlliances */
    private function validateLinkedAlliances(TerritoryPlan $plan, array $planAlliances): void
    {
        $scope = $plan->scope;
        $kingdomId = $plan->kingdom_id;
        $ownerAllianceId = $plan->owner_alliance_id;

        if ($scope === TerritoryPlanScope::Alliance) {
            $only = $planAlliances[0] ?? null;
            if (
                count($planAlliances) !== 1
                || ! is_array($only)
                || $only['alliance_id'] !== $ownerAllianceId
                || $only['external_name'] !== null
            ) {
                throw ValidationException::withMessages([
                    'alliances' => 'An Alliance-scoped plan must contain exactly its owning Alliance layer.',
                ]);
            }
        }

        foreach ($planAlliances as $planAlliance) {
            $allianceId = $planAlliance['alliance_id'];
            if (! is_string($allianceId)) {
                continue;
            }

            $reference = $this->alliances->lockCurrent($allianceId);
            if ($reference->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'alliances' => 'Linked Alliances must belong to the plan Kingdom.',
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $planAlliances
     * @param  list<array<string, mixed>>  $objects
     */
    private function validateGovernorIdentities(
        TerritoryPlan $plan,
        array $planAlliances,
        array $objects,
    ): void {
        $kingdomId = $plan->kingdom_id;
        $allianceByKey = [];
        foreach ($planAlliances as $planAlliance) {
            $allianceByKey[$planAlliance['key']] = $planAlliance['alliance_id'];
        }

        $players = [];
        foreach ($objects as $object) {
            if (! is_string($object['player_id'])) {
                continue;
            }

            $playerId = $object['player_id'];
            $linkedAllianceId = $allianceByKey[$object['alliance_key']] ?? null;
            if (! is_string($linkedAllianceId)) {
                throw ValidationException::withMessages([
                    'objects' => 'A linked Governor can only be placed on a linked Alliance layer.',
                ]);
            }

            if (isset($players[$playerId]) && $players[$playerId] !== $linkedAllianceId) {
                throw ValidationException::withMessages([
                    'objects' => 'A linked Governor cannot belong to multiple Alliance layers in one plan.',
                ]);
            }

            $players[$playerId] = $linkedAllianceId;
        }

        ksort($players);
        foreach ($players as $playerId => $allianceId) {
            $player = $this->players->lockCurrent($playerId);
            if ($player->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'objects' => 'Linked Governors must belong to the plan Kingdom.',
                ]);
            }

            if (! $this->memberships->lockActiveMember($allianceId, $playerId)) {
                throw ValidationException::withMessages([
                    'objects' => 'Linked Governors must be active members of their planned Alliance layer.',
                ]);
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
