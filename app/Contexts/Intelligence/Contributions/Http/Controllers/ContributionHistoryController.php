<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Contexts\Intelligence\Contributions\Queries\PlayerContributionHistoryQuery;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventContributionIntelligenceQuery;
use App\Shared\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContributionHistoryController extends Controller
{
    public function __construct(private PlayerContext $playerContext) {}

    public function index(
        Request $request,
        PlayerContributionHistoryQuery $history,
        EventContributionIntelligenceQuery $intelligence,
    ): Response {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

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

        return Inertia::render('Contributions/History', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'player' => [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
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
