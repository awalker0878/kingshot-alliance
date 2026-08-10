<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\TransitionKingdomAllianceDiplomacy;
use App\Domain\Kingdoms\Enums\KingdomAllianceDiplomacyState;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacy;
use App\Domain\Kingdoms\Models\KingdomAllianceDiplomacyTransition;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\KingdomAllianceDiplomacyQuery;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceDiplomacyController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        KingdomAllianceDiplomacyQuery $diplomacy,
        string $tracking,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        $tracked = $diplomacy->tracking($alliance, $tracking);
        $relationship = $tracked->diplomacy;
        $history = $diplomacy->history($alliance, $tracking);

        return Inertia::render('Alliance/KingdomAllianceDiplomacy', [
            'alliance' => $this->allianceSummary($alliance),
            'tracking' => $this->trackingSummary($tracked, $alliance),
            'states' => array_map(
                static fn (KingdomAllianceDiplomacyState $state): string => $state->value,
                KingdomAllianceDiplomacyState::cases(),
            ),
            'current' => $this->relationshipSummary($relationship, $diplomacy),
            'historyLimit' => KingdomAllianceDiplomacyQuery::HISTORY_LIMIT,
            'history' => $history
                ->map(fn (KingdomAllianceDiplomacyTransition $transition): array => $this->transitionRow($transition))
                ->values(),
        ]);
    }

    public function transition(
        Request $request,
        AllianceContext $context,
        TransitionKingdomAllianceDiplomacy $transition,
        string $tracking,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $state = KingdomAllianceDiplomacyState::from($validated['state']);
        unset($validated['state']);

        $transition->handle($context->alliance(), $this->user($request), $tracking, $state, $validated);

        return back()->with('status', 'kingdom-alliance-diplomacy-transitioned');
    }

    /**
     * @return array{
     *   state: string,
     *   effective_at: string,
     *   review_at?: string|null,
     *   expires_at?: string|null,
     *   terms?: string|null,
     *   rationale?: string|null
     * }
     */
    private function validated(Request $request): array
    {
        /** @var array{state: string, effective_at: string, review_at?: string|null, expires_at?: string|null, terms?: string|null, rationale?: string|null} $validated */
        $validated = $request->validate([
            'state' => ['required', new Enum(KingdomAllianceDiplomacyState::class)],
            'effective_at' => ['required', 'date'],
            'review_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'rationale' => ['nullable', 'string', 'max:5000'],
        ]);

        return $validated;
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function allianceSummary(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    /** @return array<string, mixed> */
    private function trackingSummary(TrackedKingdomAlliance $tracking, Alliance $alliance): array
    {
        return [
            'id' => (string) $tracking->id,
            'name' => (string) $tracking->kingdomAlliance->current_name,
            'tag' => $tracking->kingdomAlliance->current_tag,
            'state' => $tracking->state->value,
            'kingdom' => (string) $tracking->kingdom->number,
            'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $tracking->kingdom_id,
        ];
    }

    /** @return array<string, mixed> */
    private function relationshipSummary(
        ?KingdomAllianceDiplomacy $relationship,
        KingdomAllianceDiplomacyQuery $diplomacy,
    ): array {
        if (! $relationship instanceof KingdomAllianceDiplomacy) {
            return [
                'exists' => false,
                'state' => KingdomAllianceDiplomacyState::Unknown->value,
                'effectiveAt' => null,
                'reviewAt' => null,
                'expiresAt' => null,
                'needsReview' => false,
                'terms' => null,
                'rationale' => null,
                'lastActorName' => null,
            ];
        }

        return [
            'exists' => true,
            'state' => $relationship->current_state->value,
            'effectiveAt' => $relationship->effective_at->toIso8601String(),
            'reviewAt' => $relationship->review_at?->toIso8601String(),
            'expiresAt' => $relationship->expires_at?->toIso8601String(),
            'needsReview' => $diplomacy->needsReview($relationship),
            'terms' => $relationship->terms,
            'rationale' => $relationship->rationale,
            'lastActorName' => $relationship->lastTransitionUser?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function transitionRow(KingdomAllianceDiplomacyTransition $transition): array
    {
        return [
            'id' => (string) $transition->id,
            'fromState' => $transition->from_state->value,
            'toState' => $transition->to_state->value,
            'effectiveAt' => $transition->effective_at->toIso8601String(),
            'reviewAt' => $transition->review_at?->toIso8601String(),
            'expiresAt' => $transition->expires_at?->toIso8601String(),
            'terms' => $transition->terms,
            'rationale' => $transition->rationale,
            'actorName' => $transition->actor?->name,
            'recordedAt' => $transition->created_at->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
