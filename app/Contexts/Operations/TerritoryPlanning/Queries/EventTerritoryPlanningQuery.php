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
            return ['supported' => false, 'availableRevisions' => [], 'attachments' => []];
        }

        if (! $this->canViewTarget($actorPlayerId, $event)) {
            return ['supported' => true, 'availableRevisions' => [], 'attachments' => []];
        }

        $plansQuery = TerritoryPlan::query()->where('status', '!=', 'archived');
        if ($event->scope === EventScope::Alliance) {
            $plansQuery
                ->where('scope', TerritoryPlanScope::Alliance->value)
                ->where('owner_alliance_id', $event->alliance_id);
        } else {
            $plansQuery
                ->where('scope', TerritoryPlanScope::Kingdom->value)
                ->where('kingdom_id', $event->kingdom_id);
        }

        $planIds = [];
        $planNames = [];
        foreach ($plansQuery->orderBy('name')->get(['id', 'name']) as $plan) {
            $planIds[] = $plan->id;
            $planNames[$plan->id] = $plan->name;
        }

        $availableRevisions = [];
        if ($planIds !== []) {
            foreach (
                TerritoryPlanRevision::query()
                    ->whereIn('territory_plan_id', $planIds)
                    ->orderByDesc('published_at')
                    ->limit(200)
                    ->get() as $revision
            ) {
                $availableRevisions[] = [
                    'id' => $revision->id,
                    'planId' => $revision->territory_plan_id,
                    'planName' => $planNames[$revision->territory_plan_id] ?? 'Territory plan',
                    'revisionNumber' => $revision->revision_number,
                    'mapDatasetId' => $revision->map_dataset_id,
                    'mapDatasetChecksum' => $revision->map_dataset_checksum,
                    'publishedAt' => $revision->published_at->toIso8601String(),
                ];
            }
        }

        $occurrenceIds = [];
        foreach ($event->occurrences as $occurrence) {
            $occurrenceIds[] = (string) $occurrence->id;
        }
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

        $revisionIds = [];
        foreach ($links as $link) {
            $revisionIds[$link->territory_plan_revision_id] = true;
        }

        /** @var array<string, TerritoryPlanRevision> $linkedRevisions */
        $linkedRevisions = [];
        if ($revisionIds !== []) {
            foreach (TerritoryPlanRevision::query()->whereIn('id', array_keys($revisionIds))->get() as $revision) {
                $linkedRevisions[$revision->id] = $revision;
            }
        }

        $linkedPlanIds = [];
        foreach ($linkedRevisions as $revision) {
            $linkedPlanIds[$revision->territory_plan_id] = true;
        }

        /** @var array<string, TerritoryPlan> $linkedPlans */
        $linkedPlans = [];
        if ($linkedPlanIds !== []) {
            foreach (TerritoryPlan::query()->whereIn('id', array_keys($linkedPlanIds))->get(['id', 'name']) as $plan) {
                $linkedPlans[$plan->id] = $plan;
            }
        }

        $attachments = [];
        foreach ($links as $link) {
            $revision = $linkedRevisions[$link->territory_plan_revision_id] ?? null;
            if (! $revision instanceof TerritoryPlanRevision) {
                continue;
            }
            $plan = $linkedPlans[$revision->territory_plan_id] ?? null;
            $attachments[] = [
                'id' => $link->id,
                'occurrenceId' => $link->event_occurrence_id,
                'purpose' => $link->purpose,
                'revisionId' => $revision->id,
                'planId' => $revision->territory_plan_id,
                'planName' => $plan instanceof TerritoryPlan ? $plan->name : 'Territory plan',
                'revisionNumber' => $revision->revision_number,
                'publishedAt' => $revision->published_at->toIso8601String(),
            ];
        }

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
