<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Observations\Actions\InvalidateKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Queries\KingdomAllianceObservationQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceObservationController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceObservationQuery $observations,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
        PlayerReferenceQuery $players,
        AccountIdentityQuery $accounts,
        string $tracking,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }
        $canManage = $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage);
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $allianceKingdom = $kingdoms->require($alliance->kingdomId);
        $tracked = $observations->tracking($alliance->allianceId, $tracking);
        $trackedKingdom = $kingdoms->require((string) $tracked->kingdom_id);
        $trackedAlliance = $kingdomAlliances->require((string) $tracked->kingdom_alliance_id);
        $latest = $observations->latestAccepted($alliance->allianceId, $tracking);
        $history = $observations->history($alliance->allianceId, $tracking, $canManage);
        $actorIds = [];
        foreach ($history as $observation) {
            if ($observation->actor_player_id !== null) {
                $actorIds[] = (string) $observation->actor_player_id;
            }
            if ($observation->invalidated_by_player_id !== null) {
                $actorIds[] = (string) $observation->invalidated_by_player_id;
            }
        }
        $actorRefs = $players->byIds(array_values(array_unique($actorIds)));

        return Inertia::render('Intelligence/KingdomWatch/History', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $allianceKingdom->number],
            'canManage' => $canManage,
            'tracking' => [
                'name' => $trackedAlliance->currentName,
                'tag' => $trackedAlliance->currentTag,
                'state' => $tracked->state->value,
                'kingdom' => (string) $trackedKingdom->number,
                'contextCurrent' => $alliance->kingdomId === $tracked->kingdom_id,
                ...($canManage ? ['id' => (string) $tracked->id] : []),
            ],
            'freshness' => $observations->freshness($latest),
            'freshDays' => KingdomAllianceObservationQuery::FRESH_DAYS,
            'latest' => $latest === null ? null : $this->observationRow($latest, false, $actorRefs),
            'history' => $history->map(fn (KingdomAllianceObservation $observation): array => $this->observationRow($observation, $canManage, $actorRefs))->values(),
        ]);
    }

    public function store(Request $request, AllianceContext $context, RecordKingdomAllianceObservation $record, string $tracking): RedirectResponse
    {
        $scope = $context->scope();
        $record->handle($scope->allianceId, $scope->playerId, $tracking, $this->validatedObservation($request));

        return back()->with('actionReceipt', $this->receipt('kingdom-alliance-observation-recorded'));
    }

    public function invalidate(Request $request, AllianceContext $context, InvalidateKingdomAllianceObservation $invalidate, string $tracking, string $observation): RedirectResponse
    {
        /** @var array{reason:string} $validated */
        $validated = $request->validate(['reason' => ['required', 'string', 'max:5000']]);
        $scope = $context->scope();
        $invalidate->handle($scope->allianceId, $scope->playerId, $tracking, $observation, $validated['reason']);

        return back()->with('actionReceipt', $this->receipt('kingdom-alliance-observation-invalidated'));
    }

    /** @return array{observed_name:string,observed_tag?:string|null,power?:string|null,member_count?:int|null,captured_at:string,corrects_observation_id?:string|null,correction_reason?:string|null} */
    private function validatedObservation(Request $request): array
    {
        /** @var array{observed_name:string,observed_tag?:string|null,power?:string|null,member_count?:int|null,captured_at:string,corrects_observation_id?:string|null,correction_reason?:string|null} $validated */
        $validated = $request->validate([
            'observed_name' => ['required', 'string', 'max:160'],
            'observed_tag' => ['nullable', 'string', 'max:32'],
            'power' => ['nullable', 'regex:/^\\d+$/', 'max:19'],
            'member_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'captured_at' => ['required', 'date'],
            'corrects_observation_id' => ['nullable', 'string', 'max:26'],
            'correction_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        return $validated;
    }

    /**
     * @param  array<string, PlayerReference>  $actors
     * @return array<string, mixed>
     */
    private function observationRow(KingdomAllianceObservation $observation, bool $includePrivate, array $actors): array
    {
        $row = [
            'observedName' => $observation->observed_name,
            'observedTag' => $observation->observed_tag,
            'power' => $observation->power === null ? null : (string) $observation->power,
            'memberCount' => $observation->member_count,
            'capturedAt' => $observation->captured_at->toIso8601String(),
            'source' => $observation->source,
        ];
        if ($includePrivate) {
            $actor = $observation->actor_player_id === null ? null : ($actors[(string) $observation->actor_player_id] ?? null);
            $invalidator = $observation->invalidated_by_player_id === null ? null : ($actors[(string) $observation->invalidated_by_player_id] ?? null);
            $row += [
                'id' => (string) $observation->id,
                'actorName' => $actor instanceof PlayerReference ? $actor->currentName : null,
                'correctsObservationId' => $observation->corrects_observation_id,
                'invalidatedAt' => $observation->invalidated_at?->toIso8601String(),
                'invalidatedByName' => $invalidator instanceof PlayerReference ? $invalidator->currentName : null,
                'invalidationReason' => $observation->invalidation_reason,
                'sourceSubscriptionId' => $observation->source_subscription_id,
                'sourceBatchId' => $observation->source_batch_id,
                'sourceAdapterKey' => $observation->source_adapter_key,
                'sourceAdapterVersion' => $observation->source_adapter_version,
                'sourceRecordId' => $observation->source_record_id,
                'sourceIdentityHash' => $observation->source_identity_hash,
                'sourcePayloadHash' => $observation->source_payload_hash,
            ];
        }

        return $row;
    }
}
