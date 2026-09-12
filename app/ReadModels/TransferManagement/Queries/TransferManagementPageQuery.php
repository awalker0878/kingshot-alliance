<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\ReadModels\TransferManagement\Enums\TransferCatalogueKind;
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
        private TransferManagementCatalogueQuery $catalogues,
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

    /**
     * @param  array<string,?string>  $catalogueCursors
     * @return array<string,mixed>
     */
    public function management(string $actorPlayerId, string $allianceId, ?string $cursor = null, ?string $planId = null, array $catalogueCursors = []): array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        $alliance = $this->alliances->require($allianceId);
        $kingdom = $this->kingdoms->require($alliance->kingdomId);
        $selected = $planId === null ? ($this->plans->mutableForAlliance($allianceId) ?? $this->plans->currentForAlliance($allianceId))
            : TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->with(['homeKingdom', 'window'])->firstOrFail();
        $mutable = $selected !== null && in_array($selected->state, [TransferPlanState::Draft, TransferPlanState::Open], true)
            && $selected->home_kingdom_id === $alliance->kingdomId ? $selected : null;
        $participantPage = $selected === null ? new PageSlice([], null, TransferParticipantQuery::PAGE_SIZE)
            : $this->participants->page($actorPlayerId, $allianceId, (string) $selected->id, true, $cursor, TransferPermission::Manage);
        $catalogues = [];
        foreach (TransferCatalogueKind::cases() as $kind) {
            $catalogues[$kind->value] = $this->catalogues->page($actorPlayerId, $allianceId, $kind,
                in_array($kind, [TransferCatalogueKind::Windows, TransferCatalogueKind::Plans], true) ? null : $selected?->id,
                $catalogueCursors[$kind->value] ?? null);
        }

        return [
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'selectedPlan' => $selected === null ? null : $this->presenter->plan($selected),
            'mutablePlan' => $mutable === null ? null : $this->presenter->plan($mutable),
            'catalogues' => $catalogues,
            'participantSummary' => $selected === null ? null : $this->participants->summary($actorPlayerId, $allianceId, (string) $selected->id, true, TransferPermission::Manage),
            'participants' => [...$participantPage->toArray(), 'items' => array_map(fn (TransferParticipant $p): array => $this->presenter->participant($p, true), $participantPage->items)],

        ];
    }
}
