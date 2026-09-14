<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRecoveryDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TerritoryRecoveryDrafts
{
    private const MAX_BYTES = 5_000_000;

    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
    ) {}

    /**
     * Recovery deliberately accepts an invalid working layout. It is not a plan save and never
     * becomes a validated head until the normal SaveTerritoryPlan path accepts it.
     *
     * @param  array<string,mixed>  $document
     * @return array<string,mixed>
     */
    public function store(
        string $actorPlayerId,
        string $planId,
        int $baseRevision,
        string $mapDatasetId,
        string $mapDatasetChecksum,
        array $document,
    ): array {
        if ($baseRevision < 1) {
            throw ValidationException::withMessages(['base_revision' => 'Recovery base revision must be positive.']);
        }
        $encoded = $this->canonicalJson($document);
        if (strlen($encoded) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['document' => 'Recovery document exceeds the five megabyte limit.']);
        }
        $checksum = hash('sha256', $encoded);

        return DB::transaction(function () use (
            $actorPlayerId,
            $planId,
            $baseRevision,
            $mapDatasetId,
            $mapDatasetChecksum,
            $document,
            $checksum,
        ): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            if ($context->plan->status === TerritoryPlanStatus::Archived) {
                throw ValidationException::withMessages(['plan' => 'Archived plans cannot store recovery drafts.']);
            }
            if ($context->plan->map_dataset_id !== $mapDatasetId
                || ! hash_equals((string) $context->plan->map_dataset_checksum, $mapDatasetChecksum)) {
                throw ValidationException::withMessages(['map_dataset_id' => 'Recovery must use the plan\'s pinned map dataset.']);
            }

            TerritoryRecoveryDraft::query()
                ->where('territory_plan_id', $planId)
                ->where('actor_player_id', $actorPlayerId)
                ->where('expires_at', '<=', now())
                ->delete();

            $draft = TerritoryRecoveryDraft::query()->updateOrCreate(
                ['territory_plan_id' => $planId, 'actor_player_id' => $actorPlayerId],
                [
                    'base_revision' => $baseRevision,
                    'map_dataset_id' => $mapDatasetId,
                    'map_dataset_checksum' => $mapDatasetChecksum,
                    'document' => $document,
                    'document_checksum' => $checksum,
                    'expires_at' => now()->addDays(7),
                ],
            );

            return $this->payload($draft, (int) $context->plan->revision);
        });
    }

    /** @return array<string,mixed>|null */
    public function read(string $actorPlayerId, string $planId): ?array
    {
        return DB::transaction(function () use ($actorPlayerId, $planId): ?array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            TerritoryRecoveryDraft::query()
                ->where('territory_plan_id', $planId)
                ->where('actor_player_id', $actorPlayerId)
                ->where('expires_at', '<=', now())
                ->delete();
            $draft = TerritoryRecoveryDraft::query()
                ->where('territory_plan_id', $planId)
                ->where('actor_player_id', $actorPlayerId)
                ->first();
            if ($draft === null) {
                return null;
            }
            $encoded = $this->canonicalJson($draft->document);
            if (! hash_equals($draft->document_checksum, hash('sha256', $encoded))) {
                throw new \LogicException('Territory recovery draft integrity failed.');
            }

            return $this->payload($draft, (int) $context->plan->revision);
        });
    }

    public function delete(string $actorPlayerId, string $planId): bool
    {
        return DB::transaction(function () use ($actorPlayerId, $planId): bool {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);

            return TerritoryRecoveryDraft::query()
                ->where('territory_plan_id', $planId)
                ->where('actor_player_id', $actorPlayerId)
                ->delete() > 0;
        });
    }

    private function canonicalJson(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }
            ksort($item);

            return array_map($normalize, $item);
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR);
    }

    /** @return array<string,mixed> */
    private function payload(TerritoryRecoveryDraft $draft, int $currentRevision): array
    {
        return [
            'id' => (string) $draft->id,
            'base_revision' => $draft->base_revision,
            'current_revision' => $currentRevision,
            'stale' => $draft->base_revision !== $currentRevision,
            'map_dataset_id' => $draft->map_dataset_id,
            'map_dataset_checksum' => $draft->map_dataset_checksum,
            'document_checksum' => $draft->document_checksum,
            'document' => $draft->document,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'expires_at' => $draft->expires_at?->toIso8601String(),
        ];
    }
}
