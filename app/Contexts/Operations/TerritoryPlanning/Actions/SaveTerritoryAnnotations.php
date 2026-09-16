<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAnnotation;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryAnnotationContract;
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
        private TerritoryAnnotationContract $annotations,
        private TerritoryPlanSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $annotations
     * @return array{revision:int,snapshot:array<string,mixed>,layout_checksum:string}
     */
    public function handle(string $actorPlayerId, string $planId, int $expectedRevision, array $annotations): array
    {
        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $annotations): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->status === TerritoryPlanStatus::Archived) {
                throw ValidationException::withMessages(['plan' => 'Archived plans are read-only.']);
            }
            if ($context->plan->revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $context->plan->revision);
            }

            $normalized = $this->annotations->normalize(
                $annotations,
                $this->allianceKeys($context->plan->planAlliances()->pluck('plan_key')->all()),
            );

            TerritoryPlanAnnotation::query()->where('territory_plan_id', $planId)->delete();
            foreach ($normalized as $annotation) {
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
                ['revision' => $expectedRevision + 1, 'annotation_count' => count($normalized)],
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
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function allianceKeys(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new \LogicException('Persisted Territory Alliance keys are invalid.');
            }
            $keys[] = $value;
        }

        return $keys;
    }
}
