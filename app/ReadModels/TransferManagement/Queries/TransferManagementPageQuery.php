<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferCohortQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferGroupQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferKingdomConditionQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferWindowQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\TransferManagement\Presenters\TransferManagementPresenter;
use App\Shared\Infrastructure\Pagination\PageSlice;
use Illuminate\Auth\Access\AuthorizationException;

/** Authorized read composition; business mutations remain with KingdomTransfers. */
final readonly class TransferManagementPageQuery
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private TransferAuthorization $authorization,
        private TransferPlanQuery $plans,
        private TransferParticipantQuery $participants,
        private TransferCohortQuery $cohorts,
        private TransferWindowQuery $windows,
        private TransferGroupQuery $groups,
        private TransferKingdomConditionQuery $conditions,
        private RosterEntryQuery $roster,
        private PlayerMembershipQuery $memberships,
        private PlayerReferenceQuery $players,
        private TransferManagementPresenter $presenter,
    ) {}

    /** @return array<string,mixed> */
    public function overview(string $actorPlayerId, string $allianceId, ?string $cursor = null): array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }
        $alliance = $this->alliances->require($allianceId);
        $kingdom = $this->kingdoms->require($alliance->kingdomId);
        $current = $this->plans->currentForAlliance($allianceId);
        $participantPage = $current === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE) : $this->participants->page($actorPlayerId, $allianceId, (string) $current->id, false, $cursor);

        return [
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'canManage' => $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::Manage),
            'plan' => $current === null ? null : $this->presenter->plan($current),
            'participantSummary' => $current === null ? null : $this->participants->summary($actorPlayerId, $allianceId, (string) $current->id),
            'participants' => [...$participantPage->toArray(), 'items' => array_map(fn (TransferParticipant $p): array => $this->presenter->participant($p, false), $participantPage->items)],
        ];
    }

    /** @return array<string,mixed> */
    public function management(string $actorPlayerId, string $allianceId, ?string $cursor = null): array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        $alliance = $this->alliances->require($allianceId);
        $kingdom = $this->kingdoms->require($alliance->kingdomId);
        $mutable = $this->plans->mutableForAlliance($allianceId);
        $participantPage = $mutable === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE) : $this->participants->page($actorPlayerId, $allianceId, (string) $mutable->id, true, $cursor, TransferPermission::Manage);
        $rosterOptions = $this->roster->activeOrTracked($allianceId);
        $memberIds = $this->memberships->activePlayerIds($allianceId);
        $refs = $this->players->byIds(array_values(array_unique(array_merge(
            $memberIds,
            array_map(static fn (RosterEntryReference $e): string => $e->playerId, $rosterOptions),
        ))));
        $windowRows = $this->windows->forAlliance($allianceId);
        $selectedWindow = $mutable?->window;
        $capacityRows = $selectedWindow === null
            ? collect()
            : TransferKingdomCapacityObservation::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_window_id', $selectedWindow->id)
                ->with('kingdom:id,number')
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->get();

        return [
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'plans' => $this->plans->forAlliance($allianceId)->map(fn (TransferPlan $p): array => $this->presenter->plan($p))->all(),
            'mutablePlan' => $mutable === null ? null : $this->presenter->plan($mutable),
            'windows' => $windowRows->map(fn (TransferWindow $w): array => $this->presenter->window($w))->all(),
            'officialGroups' => $selectedWindow === null ? [] : $this->groups->historyForWindow($allianceId, (string) $selectedWindow->id)->map(fn (TransferGroup $g): array => $this->presenter->officialGroup($g))->all(),
            'conditions' => $selectedWindow === null ? [] : $this->conditions->forWindow($allianceId, (string) $selectedWindow->id)->map(fn (TransferKingdomConditionObservation $c): array => $this->presenter->condition($c))->all(),
            'capacities' => $capacityRows->map(fn (TransferKingdomCapacityObservation $c): array => $this->presenter->capacity($c))->all(),
            'cohorts' => $mutable === null ? [] : $this->cohorts->forPlan($allianceId, (string) $mutable->id, true)->map(fn (TransferCohort $c): array => $this->presenter->cohort($c, true))->all(),
            'participantSummary' => $mutable === null ? null : $this->participants->summary($actorPlayerId, $allianceId, (string) $mutable->id, true, TransferPermission::Manage),
            'participants' => [...$participantPage->toArray(), 'items' => array_map(fn (TransferParticipant $p): array => $this->presenter->participant($p, true), $participantPage->items)],
            'rosterOptions' => array_values(array_map(fn (RosterEntryReference $e): array => [
                'id' => $e->rosterEntryId,
                'name' => $e->observedName,
                'gamePlayerId' => $refs[$e->playerId]->gamePlayerId ?? null,
                'playerId' => $e->playerId,
            ], $rosterOptions)),
            'players' => array_values(array_map(static fn (string $id): array => [
                'id' => $id,
                'name' => $refs[$id]->currentName ?? $id,
            ], $memberIds)),
        ];
    }
}
