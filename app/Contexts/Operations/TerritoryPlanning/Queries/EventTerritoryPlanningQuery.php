<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\EventTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;

final readonly class EventTerritoryPlanningQuery
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
    ) {}

    /**
     * @return array{
     *     supported: bool,
     *     availableRevisions: list<array<string, mixed>>,
     *     attachments: list<array<string, mixed>>
     * }
     */
    public function management(string $actorPlayerId, Event $event): array
    {
        if ($event->scope === EventScope::Player) {
            return [
                'supported' => false,
                'availableRevisions' => [],
                'attachments' => [],
            ];
        }

        if (! $this->canViewTarget($actorPlayerId, $event)) {
            return [
                'supported' => true,
                'availableRevisions' => [],
                'attachments' => [],
            ];
        }

        $plans = TerritoryPlan::query()
            ->where('status', '!=', 'archived')
            ->when(
                $event->scope === EventScope::Alliance,
                static fn ($query) => $query
                    ->where('scope', TerritoryPlanScope::Alliance->value)
                    ->where('owner_alliance_id', $event->alliance_id),
                static fn ($query) => $query
                    ->where('scope', TerritoryPlanScope::Kingdom->value)
                    ->where('kingdom_id', $event->kingdom_id),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'scope', 'kingdom_id', 'owner_alliance_id']);
        $planIds = $plans->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $planNames = $plans->mapWithKeys(
            static fn (TerritoryPlan $plan): array => [(string) $plan->id => (string) $plan->name],
        );

        $availableRevisions = $planIds === []
            ? []
            : TerritoryPlanRevision::query()
                ->whereIn('territory_plan_id', $planIds)
                ->orderByDesc('published_at')
                ->limit(200)
                ->get()
                ->map(static fn (TerritoryPlanRevision $revision): array => [
                    'id' => (string) $revision->id,
                    'planId' => (string) $revision->territory_plan_id,
                    'planName' => (string) ($planNames->get((string) $revision->territory_plan_id) ?? 'Territory plan'),
                    'revisionNumber' => (int) $revision->revision_number,
                    'mapDatasetId' => (string) $revision->map_dataset_id,
                    'mapDatasetChecksum' => (string) $revision->map_dataset_checksum,
                    'publishedAt' => $revision->published_at?->toIso8601String(),
                ])
                ->values()
                ->all();

        $occurrenceIds = $event->occurrences
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        if ($occurrenceIds === []) {
            return [
                'supported' => true,
                'availableRevisions' => $availableRevisions,
                'attachments' => [],
            ];
        }

        $links = EventTerritoryPlanRevision::query()
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->orderBy('event_occurrence_id')
            ->orderBy('purpose')
            ->get();
        $revisionIds = $links
            ->pluck('territory_plan_revision_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $linkedRevisions = $revisionIds === []
            ? collect()
            : TerritoryPlanRevision::query()
                ->whereIn('id', $revisionIds)
                ->get()
                ->keyBy('id');
        $linkedPlanIds = $linkedRevisions
            ->pluck('territory_plan_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $linkedPlans = $linkedPlanIds === []
            ? collect()
            : TerritoryPlan::query()
                ->whereIn('id', $linkedPlanIds)
                ->get(['id', 'name'])
                ->keyBy('id');

        $attachments = $links->map(function (EventTerritoryPlanRevision $link) use ($linkedRevisions, $linkedPlans): array {
            $revision = $linkedRevisions->get((string) $link->territory_plan_revision_id);
            if (! $revision instanceof TerritoryPlanRevision) {
                return [];
            }
            $plan = $linkedPlans->get((string) $revision->territory_plan_id);

            return [
                'id' => (string) $link->id,
                'occurrenceId' => (string) $link->event_occurrence_id,
                'purpose' => (string) $link->purpose,
                'revisionId' => (string) $revision->id,
                'planId' => (string) $revision->territory_plan_id,
                'planName' => $plan instanceof TerritoryPlan ? (string) $plan->name : 'Territory plan',
                'revisionNumber' => (int) $revision->revision_number,
                'publishedAt' => $revision->published_at?->toIso8601String(),
            ];
        })->filter(static fn (array $attachment): bool => $attachment !== [])->values()->all();

        return [
            'supported' => true,
            'availableRevisions' => $availableRevisions,
            'attachments' => $attachments,
        ];
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
