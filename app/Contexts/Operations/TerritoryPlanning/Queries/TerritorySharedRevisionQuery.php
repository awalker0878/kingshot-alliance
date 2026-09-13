<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class TerritorySharedRevisionQuery
{
    public function __construct(private TerritoryPlanWriteState $state, private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots) {}

    /** @return array<string,mixed> */
    public function get(string $actorPlayerId, string $shareId, string $token): array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actorPlayerId, $shareId, $token): array {
            $route = TerritoryShare::query()->whereKey($shareId)->firstOrFail(['id', 'territory_plan_id']);
            // Same scope-before-plan-before-share lock order as revoke/create. Revocation is never a cached grant.
            $context = $this->state->lock($actorPlayerId, (string) $route->territory_plan_id);
            $this->authorization->authorizeView($context);
            $share = TerritoryShare::query()->whereKey($shareId)->lockForUpdate()->firstOrFail();
            if ($share->territory_plan_id !== $context->plan->id || $share->recipient_player_id !== $actorPlayerId
                || $share->revoked_at !== null || ! $share->expires_at->isFuture()
                || ! hash_equals((string) $share->token_hash, hash('sha256', $token))) {
                throw new AuthorizationException;
            }
            $revision = TerritoryPlanRevision::query()->where('territory_plan_id', $context->plan->id)
                ->whereKey($share->territory_plan_revision_id)->firstOrFail();
            $snapshot = $revision->snapshot;
            if (! hash_equals($revision->snapshot_checksum, $this->snapshots->checksum($snapshot))) {
                throw new AuthorizationException('The published snapshot failed integrity verification.');
            }
            $keys = $share->alliance_keys;
            $objects = array_values(array_filter($snapshot['objects'] ?? [], static fn (array $object): bool => in_array($object['alliance_key'] ?? null, $keys, true)));
            $groupKeys = array_filter(array_column($objects, 'group_key'));
            // A partial share returns an explicit allowlist; hidden annotations/preferences/identities cannot escape via top-level metadata.
            $filtered = [
                'schema_version' => $snapshot['schema_version'],
                'plan' => array_intersect_key($snapshot['plan'], array_flip(['id', 'name', 'scope', 'head_revision', 'map_dataset_id', 'map_dataset_checksum'])),
                'alliances' => array_values(array_filter($snapshot['alliances'] ?? [], static fn (array $row): bool => in_array($row['key'] ?? null, $keys, true))),
                'groups' => array_values(array_filter($snapshot['groups'] ?? [], static fn (array $row): bool => in_array($row['key'] ?? null, $groupKeys, true))),
                'objects' => $objects,
                'annotations' => array_values(array_filter($snapshot['annotations'] ?? [], static fn (array $row): bool => in_array($row['alliance_key'] ?? null, $keys, true))),
            ];

            return ['share_id' => $shareId, 'revision_id' => $revision->id, 'revision_number' => $revision->revision_number,
                'snapshot_checksum' => $revision->snapshot_checksum, 'projection_checksum' => $this->snapshots->checksum($filtered),
                'expires_at' => $share->expires_at->toIso8601String(), 'snapshot' => $filtered];
        });
    }
}
