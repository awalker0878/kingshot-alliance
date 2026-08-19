<?php

declare(strict_types=1);

namespace App\ReadModels\EventHistory\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\ReadModels\EventAnalysis\Queries\EventAllianceHistoryQuery;
use App\ReadModels\EventAnalysis\Queries\EventContributionIntelligenceQuery;
use App\ReadModels\EventAnalysis\Queries\EventKingdomHistoryQuery;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EventHistoryController extends Controller
{
    public function __construct(private PlayerContext $playerContext) {}

    public function alliance(
        Request $request,
        Alliance $alliance,
        AllianceReferenceQuery $alliances,
        EventAllianceHistoryQuery $history,
        EventContributionIntelligenceQuery $intelligence,
    ): Response {
        $user = $this->user($request);
        $actor = $this->playerContext->player();
        $allianceReference = $alliances->require((string) $alliance->id);
        $validated = $this->validatedFilters($request);
        $filters = $this->filters($validated);

        return Inertia::render('Operations/Events/Chronicle', [
            'user' => $this->identity($user),
            'organization' => [
                'id' => $allianceReference->allianceId,
                'scope' => 'alliance',
                'name' => $allianceReference->name,
                'secondaryLabel' => null,
            ],
            'filters' => $this->filterPayload($validated),
            'intelligence' => $intelligence->forAlliance($actor, $allianceReference, $filters),
            'history' => $history->forAlliance($actor, $allianceReference, $filters),
        ]);
    }

    public function kingdom(
        Request $request,
        Kingdom $kingdom,
        KingdomReferenceQuery $kingdoms,
        EventKingdomHistoryQuery $history,
        EventContributionIntelligenceQuery $intelligence,
    ): Response {
        $user = $this->user($request);
        $actor = $this->playerContext->player();
        $kingdomReference = $kingdoms->require((string) $kingdom->id);
        $validated = $this->validatedFilters($request);
        $filters = $this->filters($validated);

        return Inertia::render('Operations/Events/Chronicle', [
            'user' => $this->identity($user),
            'organization' => [
                'id' => $kingdomReference->kingdomId,
                'scope' => 'kingdom',
                'name' => 'Kingdom '.(string) $kingdomReference->number,
                'secondaryLabel' => null,
            ],
            'filters' => $this->filterPayload($validated),
            'intelligence' => $intelligence->forKingdom($actor, $kingdomReference, $filters),
            'history' => $history->forKingdom($actor, $kingdomReference, $filters),
        ]);
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array{event_type_slug:?string,from:?CarbonImmutable,until:?CarbonImmutable,limit:int}
     */
    private function filters(array $validated): array
    {
        return [
            'event_type_slug' => isset($validated['event_type_slug']) ? (string) $validated['event_type_slug'] : null,
            'from' => isset($validated['from']) ? CarbonImmutable::parse((string) $validated['from']) : null,
            'until' => isset($validated['until']) ? CarbonImmutable::parse((string) $validated['until']) : null,
            'limit' => isset($validated['limit']) ? (int) $validated['limit'] : 100,
        ];
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array{eventTypeSlug:?string,from:?string,until:?string,limit:int}
     */
    private function filterPayload(array $validated): array
    {
        return [
            'eventTypeSlug' => isset($validated['event_type_slug']) ? (string) $validated['event_type_slug'] : null,
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'until' => isset($validated['until']) ? (string) $validated['until'] : null,
            'limit' => isset($validated['limit']) ? (int) $validated['limit'] : 100,
        ];
    }

    /** @return array<string,mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'event_type_slug' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /** @return array{name:string,email:string} */
    private function identity(User $user): array
    {
        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }
}
