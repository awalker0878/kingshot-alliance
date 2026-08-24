<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\TerritoryPlanning\Models\EventTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;

final readonly class PublishedEventTerritoryRevisionQuery
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
    ) {}

    /**
     * Returns only immutable published revisions attached to this authorized Event occurrence.
     * Mutable heads and unrelated revisions are never queried into the result set.
     *
     * @return list<array<string,mixed>>
     */
    public function forOccurrence(
        string $actorPlayerId,
        EventOccurrence $occurrence,
        ?string $purpose = null,
    ): array {
        $event = $occurrence->event;
        if (! $event instanceof Event || ! $this->canViewTarget($actorPlayerId, $event)) {
            return [];
        }

        $linksQuery = EventTerritoryPlanRevision::query()
            ->where('event_occurrence_id', $occurrence->id);
        if ($purpose !== null && trim($purpose) !== '') {
            $linksQuery->where('purpose', trim($purpose));
        }
        $links = $linksQuery
            ->orderBy('purpose')
            ->orderBy('id')
            ->limit(10)
            ->get();
        if ($links->isEmpty()) {
            return [];
        }

        $revisionIds = $links
            ->pluck('territory_plan_revision_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        /** @var array<string,TerritoryPlanRevision> $revisions */
        $revisions = [];
        foreach (TerritoryPlanRevision::query()->whereIn('id', $revisionIds)->get() as $revision) {
            $revisions[(string) $revision->id] = $revision;
        }

        $planIds = [];
        foreach ($revisions as $revision) {
            $planIds[] = (string) $revision->territory_plan_id;
        }
        /** @var array<string,TerritoryPlan> $plans */
        $plans = [];
        if ($planIds !== []) {
            foreach (TerritoryPlan::query()->whereIn('id', array_values(array_unique($planIds)))->get(['id', 'name']) as $plan) {
                $plans[(string) $plan->id] = $plan;
            }
        }

        $rows = [];
        foreach ($links as $link) {
            $revision = $revisions[(string) $link->territory_plan_revision_id] ?? null;
            if (! $revision instanceof TerritoryPlanRevision) {
                continue;
            }
            $plan = $plans[(string) $revision->territory_plan_id] ?? null;
            $rows[] = [
                'attachmentId' => (string) $link->id,
                'occurrenceId' => (string) $occurrence->id,
                'purpose' => (string) $link->purpose,
                'revisionId' => (string) $revision->id,
                'planId' => (string) $revision->territory_plan_id,
                'planName' => $plan instanceof TerritoryPlan ? (string) $plan->name : 'Territory plan',
                'revisionNumber' => (int) $revision->revision_number,
                'mapDatasetId' => (string) $revision->map_dataset_id,
                'mapDatasetChecksum' => (string) $revision->map_dataset_checksum,
                'publishedAt' => $revision->published_at->toIso8601String(),
            ];
        }

        return $rows;
    }

    private function canViewTarget(string $actorPlayerId, Event $event): bool
    {
        return match ($event->scope) {
            EventScope::Alliance => $event->alliance_id !== null
                && $this->allianceAuthorization->allows(
                    $actorPlayerId,
                    (string) $event->alliance_id,
                    OperationsPermission::TerritoryAllianceView,
                ),
            EventScope::Kingdom => $event->kingdom_id !== null
                && $this->kingdomAuthorization->allows(
                    $actorPlayerId,
                    (string) $event->kingdom_id,
                    OperationsPermission::TerritoryKingdomView,
                ),
            EventScope::Player => false,
        };
    }
}
