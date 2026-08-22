<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanImport;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ImportTerritoryPlan
{
    public function __construct(
        private TerritoryPlanImport $imports,
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
        private SaveTerritoryPlan $save,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $planId,
        int $expectedRevision,
        string $document,
    ): TerritoryPlanMutationReceipt {
        $preview = $this->imports->preview($document);
        if (($preview['can_commit'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'import' => 'The imported Territory layout contains blocking placement violations.',
            ]);
        }

        $map = $preview['map'] ?? null;
        if (! is_array($map)) {
            throw ValidationException::withMessages([
                'import' => 'The imported Territory layout has no valid map profile.',
            ]);
        }

        DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $map): void {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            if ((int) $context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan changed before the imported layout could be committed.',
                ]);
            }

            if (
                (string) $context->plan->map_dataset_id !== (string) ($map['id'] ?? '')
                || (string) $context->plan->map_dataset_checksum !== (string) ($map['checksum'] ?? '')
            ) {
                throw ValidationException::withMessages([
                    'import' => 'The imported layout uses a different map dataset. Rebase the layout to the plan map before importing it.',
                ]);
            }
        });

        $alliances = $preview['alliances'] ?? null;
        $groups = $preview['groups'] ?? null;
        $objects = $preview['objects'] ?? null;
        $preferences = $preview['planning_preferences'] ?? null;
        if (
            ! is_array($alliances)
            || ! is_array($groups)
            || ! is_array($objects)
            || ! is_array($preferences)
        ) {
            throw ValidationException::withMessages([
                'import' => 'The normalized imported layout is incomplete.',
            ]);
        }

        $receipt = $this->save->handle(
            $actorPlayerId,
            $planId,
            $expectedRevision,
            $alliances,
            $groups,
            $objects,
            $preferences,
        );

        $actor = $this->players->require($actorPlayerId);
        $plan = TerritoryPlan::query()->findOrFail($planId);
        $this->audit->record(
            'territory.plan.imported',
            $actor,
            $plan,
            $plan->owner_alliance_id === null ? null : (string) $plan->owner_alliance_id,
            [
                'schema_version' => (int) ($preview['schema_version'] ?? 0),
                'map_dataset_id' => (string) ($map['id'] ?? ''),
                'map_dataset_checksum' => (string) ($map['checksum'] ?? ''),
                'document_checksum' => hash('sha256', $document),
                'alliance_count' => count($alliances),
                'object_count' => count($objects),
                'result_revision' => $receipt->revision,
            ],
        );

        return $receipt;
    }
}
