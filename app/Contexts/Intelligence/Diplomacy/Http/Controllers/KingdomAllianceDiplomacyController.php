<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Actions\TransitionKingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\Intelligence\Diplomacy\Queries\KingdomAllianceDiplomacyQuery;
use App\Shared\Infrastructure\Http\Controller;
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
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceDiplomacyQuery $diplomacy,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
        PlayerReferenceQuery $players,
        AccountIdentityQuery $accounts,
        string $tracking,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $allianceKingdom = $kingdoms->require($alliance->kingdomId);
        $tracked = $diplomacy->tracking($alliance->allianceId, $tracking);
        $trackedKingdom = $kingdoms->require((string) $tracked->kingdom_id);
        $trackedAlliance = $kingdomAlliances->require((string) $tracked->kingdom_alliance_id);
        $relationship = $diplomacy->relationship($alliance->allianceId, $tracking);
        $history = $diplomacy->history($alliance->allianceId, $tracking);
        $playerIds = [];
        if ($relationship?->last_transition_player_id !== null) {
            $playerIds[] = (string) $relationship->last_transition_player_id;
        }
        foreach ($history as $transition) {
            if ($transition->actor_player_id !== null) {
                $playerIds[] = (string) $transition->actor_player_id;
            }
        }
        $playerRefs = $players->byIds(array_values(array_unique($playerIds)));

        return Inertia::render('Intelligence/KingdomWatch/Diplomacy', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $allianceKingdom->number],
            'tracking' => [
                'id' => (string) $tracked->id,
                'name' => $trackedAlliance->currentName,
                'tag' => $trackedAlliance->currentTag,
                'state' => $tracked->state->value,
                'kingdom' => (string) $trackedKingdom->number,
                'contextCurrent' => $alliance->kingdomId === $tracked->kingdom_id,
            ],
            'states' => array_map(static fn (KingdomAllianceDiplomacyState $state): string => $state->value, KingdomAllianceDiplomacyState::cases()),
            'current' => $this->relationshipSummary($relationship, $diplomacy, $playerRefs),
            'historyLimit' => KingdomAllianceDiplomacyQuery::HISTORY_LIMIT,
            'history' => $history->map(fn (KingdomAllianceDiplomacyTransition $transition): array => $this->transitionRow($transition, $playerRefs))->values(),
        ]);
    }

    public function transition(Request $request, AllianceContext $context, TransitionKingdomAllianceDiplomacy $transition, string $tracking): RedirectResponse
    {
        $validated = $this->validated($request);
        $state = KingdomAllianceDiplomacyState::from($validated['state']);
        unset($validated['state']);
        $scope = $context->scope();
        $transition->handle($scope->allianceId, $scope->playerId, $tracking, $state, $validated);

        return back()->with('status', 'kingdom-alliance-diplomacy-transitioned');
    }

    /** @return array{state:string,effective_at:string,review_at?:string|null,expires_at?:string|null,terms?:string|null,rationale?:string|null} */
    private function validated(Request $request): array
    {
        /** @var array{state:string,effective_at:string,review_at?:string|null,expires_at?:string|null,terms?:string|null,rationale?:string|null} $validated */
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

    /**
     * @param  array<string, PlayerReference>  $players
     * @return array{exists:bool,state:string,effectiveAt:string|null,reviewAt:string|null,expiresAt:string|null,needsReview:bool,terms:string|null,rationale:string|null,lastActorName:string|null}
     */
    private function relationshipSummary(?KingdomAllianceDiplomacy $relationship, KingdomAllianceDiplomacyQuery $diplomacy, array $players): array
    {
        if (! $relationship instanceof KingdomAllianceDiplomacy) {
            return ['exists' => false, 'state' => KingdomAllianceDiplomacyState::Unknown->value, 'effectiveAt' => null, 'reviewAt' => null, 'expiresAt' => null, 'needsReview' => false, 'terms' => null, 'rationale' => null, 'lastActorName' => null];
        }
        $actor = $relationship->last_transition_player_id === null ? null : ($players[(string) $relationship->last_transition_player_id] ?? null);

        return [
            'exists' => true,
            'state' => $relationship->current_state->value,
            'effectiveAt' => $relationship->effective_at->toIso8601String(),
            'reviewAt' => $relationship->review_at?->toIso8601String(),
            'expiresAt' => $relationship->expires_at?->toIso8601String(),
            'needsReview' => $diplomacy->needsReview($relationship),
            'terms' => $relationship->terms,
            'rationale' => $relationship->rationale,
            'lastActorName' => $actor?->currentName,
        ];
    }

    /**
     * @param  array<string, PlayerReference>  $players
     * @return array{id:string,fromState:string,toState:string,effectiveAt:string,reviewAt:string|null,expiresAt:string|null,terms:string|null,rationale:string|null,actorName:string|null,recordedAt:string}
     */
    private function transitionRow(KingdomAllianceDiplomacyTransition $transition, array $players): array
    {
        $actor = $transition->actor_player_id === null ? null : ($players[(string) $transition->actor_player_id] ?? null);

        return [
            'id' => (string) $transition->id,
            'fromState' => $transition->from_state->value,
            'toState' => $transition->to_state->value,
            'effectiveAt' => $transition->effective_at->toIso8601String(),
            'reviewAt' => $transition->review_at?->toIso8601String(),
            'expiresAt' => $transition->expires_at?->toIso8601String(),
            'terms' => $transition->terms,
            'rationale' => $transition->rationale,
            'actorName' => $actor?->currentName,
            'recordedAt' => $transition->created_at->toIso8601String(),
        ];
    }
}
