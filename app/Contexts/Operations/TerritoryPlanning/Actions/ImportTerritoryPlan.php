<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanImport;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
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
            throw $this->invalidImport('The imported Territory layout has no valid map profile.');
        }

        DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $map): void {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            if ($context->plan->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This plan changed before the imported layout could be committed.',
                ]);
            }

            if (
                $context->plan->map_dataset_id !== (string) ($map['id'] ?? '')
                || $context->plan->map_dataset_checksum !== (string) ($map['checksum'] ?? '')
            ) {
                throw $this->invalidImport(
                    'The imported layout uses a different map dataset. Rebase the layout to the plan map before importing it.',
                );
            }
        });

        $alliances = $this->rows($preview['alliances'] ?? null);
        $groups = $this->rows($preview['groups'] ?? null);
        $objects = $this->rows($preview['objects'] ?? null);
        $preferences = $preview['planning_preferences'] ?? null;
        if (! is_array($preferences)) {
            throw $this->invalidImport('The normalized imported layout is incomplete.');
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
            $plan->owner_alliance_id,
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

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value)) {
            throw $this->invalidImport('The normalized imported layout is incomplete.');
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw $this->invalidImport('The normalized imported layout is incomplete.');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function invalidImport(string $message): ValidationException
    {
        return ValidationException::withMessages(['import' => $message]);
    }
}
