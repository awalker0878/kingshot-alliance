<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAnnotation;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTerritoryAnnotations
{
    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $annotations
     * @return array{revision:int,snapshot:array<string,mixed>,layout_checksum:string}
     */
    public function handle(string $actorPlayerId, string $planId, int $expectedRevision, array $annotations): array
    {
        $annotations = $this->normalize($annotations);

        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $annotations): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->status === TerritoryPlanStatus::Archived) {
                throw ValidationException::withMessages(['plan' => 'Archived plans are read-only.']);
            }
            if ($context->plan->revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $context->plan->revision);
            }

            TerritoryPlanAnnotation::query()->where('territory_plan_id', $planId)->delete();
            foreach ($annotations as $annotation) {
                TerritoryPlanAnnotation::query()->create([
                    'territory_plan_id' => $planId,
                    'plan_key' => $annotation['key'],
                    'kind' => $annotation['kind'],
                    'alliance_key' => $annotation['alliance_key'],
                    'text' => $annotation['text'],
                    'coordinate_x' => $annotation['x'],
                    'coordinate_y' => $annotation['y'],
                    'target_x' => $annotation['target_x'],
                    'target_y' => $annotation['target_y'],
                    'sort_order' => $annotation['sort_order'],
                    'created_by_player_id' => $actorPlayerId,
                    'updated_by_player_id' => $actorPlayerId,
                ]);
            }

            $context->plan->forceFill([
                'revision' => $expectedRevision + 1,
                'status' => TerritoryPlanStatus::Draft,
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $this->audit->record(
                'territory.annotations.saved',
                $context->actor,
                $context->plan,
                $context->plan->owner_alliance_id,
                ['revision' => $expectedRevision + 1, 'annotation_count' => count($annotations)],
            );

            $snapshot = $this->snapshots->build($context->plan);

            return [
                'revision' => $expectedRevision + 1,
                'snapshot' => $snapshot,
                'layout_checksum' => $this->snapshots->checksum($snapshot),
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key:string,kind:string,alliance_key:?string,text:?string,x:int,y:int,target_x:?int,target_y:?int,sort_order:int}>
     */
    private function normalize(array $rows): array
    {
        if (! array_is_list($rows) || count($rows) > 500) {
            throw ValidationException::withMessages(['annotations' => 'Annotations must be a list of at most 500 entries.']);
        }
        $keys = [];
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw ValidationException::withMessages(['annotations' => 'Every annotation must be an object.']);
            }
            if (array_diff(array_keys($row), ['key', 'kind', 'alliance_key', 'text', 'x', 'y', 'target_x', 'target_y', 'sort_order']) !== []) {
                throw ValidationException::withMessages(['annotations' => 'An annotation contains unsupported fields.']);
            }
            $key = trim((string) ($row['key'] ?? ''));
            $kind = (string) ($row['kind'] ?? '');
            $text = isset($row['text']) ? trim((string) $row['text']) : null;
            $allianceKey = isset($row['alliance_key']) ? trim((string) $row['alliance_key']) : null;
            $x = $row['x'] ?? null;
            $y = $row['y'] ?? null;
            $targetX = $row['target_x'] ?? null;
            $targetY = $row['target_y'] ?? null;
            $sortOrder = $row['sort_order'] ?? $index;
            if ($key === '' || mb_strlen($key) > 120 || isset($keys[$key])) {
                throw ValidationException::withMessages(['annotations' => 'Annotation keys must be unique bounded strings.']);
            }
            if (! in_array($kind, ['label', 'line', 'arrow', 'rectangle'], true)) {
                throw ValidationException::withMessages(['annotations' => 'Annotation kind is unsupported.']);
            }
            if (! is_int($x) || ! is_int($y) || abs($x) > 1000000 || abs($y) > 1000000) {
                throw ValidationException::withMessages(['annotations' => 'Annotation coordinates must be bounded integers.']);
            }
            if (($targetX !== null && (! is_int($targetX) || abs($targetX) > 1000000)) || ($targetY !== null && (! is_int($targetY) || abs($targetY) > 1000000))) {
                throw ValidationException::withMessages(['annotations' => 'Annotation target coordinates must be bounded integers.']);
            }
            if (in_array($kind, ['line', 'arrow', 'rectangle'], true) && ($targetX === null || $targetY === null)) {
                throw ValidationException::withMessages(['annotations' => 'Shape annotations require a target coordinate.']);
            }
            if ($text !== null && mb_strlen($text) > 500) {
                throw ValidationException::withMessages(['annotations' => 'Annotation text must be at most 500 characters.']);
            }
            if ($allianceKey === '') {
                $allianceKey = null;
            }
            if ($allianceKey !== null && mb_strlen($allianceKey) > 120) {
                throw ValidationException::withMessages(['annotations' => 'Annotation Alliance keys must be bounded strings.']);
            }
            if (! is_int($sortOrder) || $sortOrder < 0 || $sortOrder > 500) {
                throw ValidationException::withMessages(['annotations' => 'Annotation sort order is out of range.']);
            }
            $keys[$key] = true;
            $normalized[] = compact('key', 'kind', 'allianceKey', 'text', 'x', 'y', 'targetX', 'targetY', 'sortOrder');
            $last = array_key_last($normalized);
            $normalized[$last]['alliance_key'] = $normalized[$last]['allianceKey'];
            $normalized[$last]['target_x'] = $normalized[$last]['targetX'];
            $normalized[$last]['target_y'] = $normalized[$last]['targetY'];
            $normalized[$last]['sort_order'] = $normalized[$last]['sortOrder'];
            unset($normalized[$last]['allianceKey'], $normalized[$last]['targetX'], $normalized[$last]['targetY'], $normalized[$last]['sortOrder']);
        }

        return $normalized;
    }
}
