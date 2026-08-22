<?php

declare(strict_types=1);

namespace App\ReadModels\ContributionHistory\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\ReadModels\ContributionHistory\PlayerContributionHistoryQuery;
use App\ReadModels\EventAnalysis\Queries\EventContributionIntelligenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContributionHistoryController extends Controller
{
    public function __construct(
        private PlayerContext $playerContext,
        private AccountIdentityQuery $accounts,
    ) {}

    public function index(
        Request $request,
        PlayerContributionHistoryQuery $history,
        EventContributionIntelligenceQuery $intelligence,
    ): Response {
        $authenticated = $request->user();
        if ($authenticated === null) {
            throw new AuthorizationException;
        }

        $account = $this->accounts->require((int) $authenticated->getAuthIdentifier());
        $player = $this->playerContext->player();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'alliance_id' => ['nullable', 'ulid'],
            'kingdom_id_at_event' => ['nullable', 'ulid'],
            'event_scope' => ['nullable', 'in:player,alliance,kingdom'],
            'event_type_slug' => ['nullable', 'string', 'max:80'],
            'event_metric_key' => ['nullable', 'string', 'max:96'],
            'participation_outcome' => ['nullable', 'in:completed,absent,excused,unresolved'],
            'contribution_category_slug' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $filters = [
            ...$validated,
            'from' => isset($validated['from']) ? CarbonImmutable::parse((string) $validated['from']) : null,
            'until' => isset($validated['until']) ? CarbonImmutable::parse((string) $validated['until']) : null,
        ];
        $intelligenceFilters = [
            'from' => $filters['from'],
            'until' => $filters['until'],
            'event_type_slug' => $validated['event_type_slug'] ?? null,
            'metric_key' => $validated['event_metric_key'] ?? null,
        ];

        return Inertia::render('Intelligence/Contributions/History', [
            'user' => [
                'name' => $account->name,
                'email' => $account->email,
            ],
            'player' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ],
            'summary' => $history->summaryForPlayer($player),
            'filters' => [
                'from' => $validated['from'] ?? null,
                'until' => $validated['until'] ?? null,
                'allianceId' => $validated['alliance_id'] ?? null,
                'kingdomIdAtEvent' => $validated['kingdom_id_at_event'] ?? null,
                'eventScope' => $validated['event_scope'] ?? null,
                'eventTypeSlug' => $validated['event_type_slug'] ?? null,
                'eventMetricKey' => $validated['event_metric_key'] ?? null,
                'participationOutcome' => $validated['participation_outcome'] ?? null,
                'contributionCategorySlug' => $validated['contribution_category_slug'] ?? null,
                'limit' => isset($validated['limit']) ? (int) $validated['limit'] : 100,
            ],
            'intelligence' => $intelligence->forPlayer($player, $intelligenceFilters),
            'history' => $history->forPlayer($player, $filters),
        ]);
    }
}
