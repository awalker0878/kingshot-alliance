<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Queries\EventAllianceHistoryQuery;
use App\Domain\Events\Queries\EventKingdomHistoryQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Http\Controllers\Controller;
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
        EventAllianceHistoryQuery $history,
    ): Response {
        $user = $this->user($request);
        $actor = $this->playerContext->player();
        $validated = $this->validatedFilters($request);

        return Inertia::render('Events/OrganizationHistory', [
            'user' => $this->identity($user),
            'organization' => [
                'id' => (string) $alliance->id,
                'scope' => 'alliance',
                'name' => (string) $alliance->name,
                'secondaryLabel' => null,
            ],
            'filters' => $this->filterPayload($validated),
            'history' => $history->forAlliance($actor, $alliance, $this->filters($validated)),
        ]);
    }

    public function kingdom(
        Request $request,
        Kingdom $kingdom,
        EventKingdomHistoryQuery $history,
    ): Response {
        $user = $this->user($request);
        $actor = $this->playerContext->player();
        $validated = $this->validatedFilters($request);

        return Inertia::render('Events/OrganizationHistory', [
            'user' => $this->identity($user),
            'organization' => [
                'id' => (string) $kingdom->id,
                'scope' => 'kingdom',
                'name' => 'Kingdom '.(string) $kingdom->number,
                'secondaryLabel' => null,
            ],
            'filters' => $this->filterPayload($validated),
            'history' => $history->forKingdom($actor, $kingdom, $this->filters($validated)),
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
