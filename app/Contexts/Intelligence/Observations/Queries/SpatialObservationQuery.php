<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Queries;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservedObject;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class SpatialObservationQuery
{
    public function __construct(private AllianceIntelligenceAuthorization $authorization) {}

    /** @return list<array<string,mixed>> */
    public function history(string $actorPlayerId, string $allianceId, string $kingdomId, int $limit = 50): array
    {
        $this->authorize($actorPlayerId, $allianceId);

        return array_values(SpatialObservation::query()
            ->where('alliance_id', $allianceId)
            ->where('kingdom_id', $kingdomId)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (SpatialObservation $observation): array => $this->summary($observation))
            ->all());
    }

    /** @return array<string,mixed>|null */
    public function latest(string $actorPlayerId, string $allianceId, string $kingdomId): ?array
    {
        $this->authorize($actorPlayerId, $allianceId);
        $observation = SpatialObservation::query()
            ->with('objects')
            ->where('alliance_id', $allianceId)
            ->where('kingdom_id', $kingdomId)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return $observation instanceof SpatialObservation ? $this->detailArray($observation) : null;
    }

    /** @return array<string,mixed> */
    public function detail(string $actorPlayerId, string $allianceId, string $kingdomId, string $observationId): array
    {
        $this->authorize($actorPlayerId, $allianceId);
        $observation = SpatialObservation::query()
            ->with('objects')
            ->where('alliance_id', $allianceId)
            ->where('kingdom_id', $kingdomId)
            ->whereKey($observationId)
            ->firstOrFail();

        return $this->detailArray($observation);
    }

    /** @return array<string,mixed> */
    private function summary(SpatialObservation $observation): array
    {
        return [
            'id' => (string) $observation->id,
            'alliance_id' => (string) $observation->alliance_id,
            'kingdom_id' => (string) $observation->kingdom_id,
            'captured_at' => $observation->captured_at->toIso8601String(),
            'coverage_kind' => $observation->coverage_kind->value,
            'completeness' => $observation->completeness->value,
            'map_dataset_id' => (string) $observation->map_dataset_id,
            'map_dataset_checksum' => (string) $observation->map_dataset_checksum,
            'source' => (string) $observation->source,
            'source_evidence_id' => $observation->source_evidence_id === null ? null : (string) $observation->source_evidence_id,
            'source_review_id' => $observation->source_review_id === null ? null : (string) $observation->source_review_id,
            'accepted_at' => $observation->accepted_at?->toIso8601String(),
            'corrects_observation_id' => $observation->corrects_observation_id === null ? null : (string) $observation->corrects_observation_id,
            'invalidated_at' => $observation->invalidated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function detailArray(SpatialObservation $observation): array
    {
        $objects = [];
        foreach ($observation->objects as $object) {
            if (! $object instanceof SpatialObservedObject) {
                continue;
            }
            $objects[] = [
                'key' => (string) $object->object_key,
                'type' => $object->object_type->value,
                'x' => (int) $object->coordinate_x,
                'y' => (int) $object->coordinate_y,
                'player_id' => $object->player_id === null ? null : (string) $object->player_id,
                'plan_local_identity' => $object->plan_local_identity === null ? null : (string) $object->plan_local_identity,
                'observed_label' => $object->observed_label === null ? null : (string) $object->observed_label,
                'identity_state' => $object->identity_state->value,
                'confidence' => $object->confidence === null ? null : (float) $object->confidence,
                'source_metadata' => $object->source_metadata ?? [],
            ];
        }

        return $this->summary($observation) + [
            'coverage_bounds' => $observation->coverage_bounds,
            'objects' => $objects,
        ];
    }

    private function authorize(string $actorPlayerId, string $allianceId): void
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }
    }
}
