<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRendition;
use DateTimeInterface;

final readonly class TerritoryArtifactQuery
{
    public function __construct(private TerritoryPlanQuery $plans) {}

    /** @return list<array<string, mixed>> */
    public function templates(string $actorPlayerId, string $planId): array
    {
        $detail = $this->plans->detail($actorPlayerId, $planId);
        $plan = $this->plan($detail);

        $rows = [];
        foreach (
            TerritoryHiveTemplate::query()
                ->where('kingdom_id', $plan['kingdom_id'])
                ->where('map_dataset_id', $plan['map_dataset_id'])
                ->where('map_dataset_checksum', $plan['map_dataset_checksum'])
                ->orderBy('name')
                ->limit(100)
                ->get() as $template
        ) {
            if (! $template instanceof TerritoryHiveTemplate) {
                continue;
            }
            $rows[] = [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'style' => (string) $template->style,
                'city_count' => (int) $template->city_count,
                'spacing' => (int) $template->spacing,
                'map_dataset_id' => (string) $template->map_dataset_id,
                'map_dataset_checksum' => (string) $template->map_dataset_checksum,
                'planning_preferences' => $this->map($template->planning_preferences),
                'created_at' => $this->date($template->created_at),
                'updated_at' => $this->date($template->updated_at),
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function renditions(string $actorPlayerId, string $planId): array
    {
        $this->plans->authorizeView($actorPlayerId, $planId);
        $rows = [];
        foreach (
            TerritoryRendition::query()
                ->where('territory_plan_id', $planId)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get() as $rendition
        ) {
            if (! $rendition instanceof TerritoryRendition) {
                continue;
            }
            $rows[] = $this->renditionMetadata($rendition);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function rendition(string $actorPlayerId, string $planId, string $renditionId): array
    {
        $this->plans->authorizeView($actorPlayerId, $planId);
        $rendition = TerritoryRendition::query()
            ->whereKey($renditionId)
            ->where('territory_plan_id', $planId)
            ->firstOrFail();

        return [
            ...$this->renditionMetadata($rendition),
            'content_base64' => (string) $rendition->content_base64,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{kingdom_id:string,map_dataset_id:string,map_dataset_checksum:string}
     */
    private function plan(array $detail): array
    {
        $plan = $detail['plan'] ?? null;
        if (! is_array($plan)) {
            throw new \LogicException('Territory plan detail is invalid.');
        }
        $kingdomId = $plan['kingdom_id'] ?? null;
        $mapId = $plan['map_dataset_id'] ?? null;
        $mapChecksum = $plan['map_dataset_checksum'] ?? null;
        if (! is_string($kingdomId) || ! is_string($mapId) || ! is_string($mapChecksum)) {
            throw new \LogicException('Territory plan detail map pin is invalid.');
        }

        return [
            'kingdom_id' => $kingdomId,
            'map_dataset_id' => $mapId,
            'map_dataset_checksum' => $mapChecksum,
        ];
    }

    /** @return array<string, mixed> */
    private function renditionMetadata(TerritoryRendition $rendition): array
    {
        return [
            'id' => (string) $rendition->id,
            'territory_plan_revision_id' => (string) $rendition->territory_plan_revision_id,
            'scope' => (string) $rendition->scope,
            'media_type' => (string) $rendition->media_type,
            'content_checksum' => (string) $rendition->content_checksum,
            'content_bytes' => (int) $rendition->content_bytes,
            'metadata' => $this->map($rendition->metadata),
            'created_at' => $this->date($rendition->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function map(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new \LogicException('Persisted Territory artifact metadata is invalid.');
        }
        $map = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \LogicException('Persisted Territory artifact metadata is invalid.');
            }
            $map[$key] = $item;
        }

        return $map;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
