<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Http\Controllers;

use App\Domain\Contributions\Queries\PlayerContributionHistoryQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ContributionHistoryController extends Controller
{
    public function __construct(private PlayerContext $playerContext) {}

    public function index(Request $request, PlayerContributionHistoryQuery $history): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $player = $this->playerContext->player();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'alliance_id' => ['nullable', 'ulid'],
            'event_scope' => ['nullable', 'in:player,alliance,kingdom'],
            'event_type_slug' => ['nullable', 'string', 'max:80'],
            'event_metric_key' => ['nullable', 'string', 'max:96'],
            'participation_outcome' => ['nullable', 'in:completed,absent,excused,unresolved'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $filters = [
            ...$validated,
            'from' => isset($validated['from']) ? CarbonImmutable::parse((string) $validated['from']) : null,
            'until' => isset($validated['until']) ? CarbonImmutable::parse((string) $validated['until']) : null,
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
            'filters' => [
                'from' => $validated['from'] ?? null,
                'until' => $validated['until'] ?? null,
                'allianceId' => $validated['alliance_id'] ?? null,
                'eventScope' => $validated['event_scope'] ?? null,
                'eventTypeSlug' => $validated['event_type_slug'] ?? null,
                'eventMetricKey' => $validated['event_metric_key'] ?? null,
                'participationOutcome' => $validated['participation_outcome'] ?? null,
                'limit' => isset($validated['limit']) ? (int) $validated['limit'] : 100,
            ],
            'history' => $history->forPlayer($player, $filters),
        ]);
    }
}
