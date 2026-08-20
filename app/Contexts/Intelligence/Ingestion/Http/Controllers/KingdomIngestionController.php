<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Ingestion\Actions\CreateKingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Actions\RejectKingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Actions\ReplayKingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Actions\TransitionKingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Queries\KingdomIngestionQuery;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomIngestionController extends Controller
{
    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomIngestionAdapterRegistry $adapters,
        KingdomIngestionQuery $ingestion,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        AccountIdentityQuery $accounts,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $allianceKingdom = $kingdoms->require($alliance->kingdomId);
        $subscriptions = $ingestion->subscriptionsForAlliance($alliance->allianceId);
        $kingdomRefs = $kingdoms->byIds(array_values($subscriptions->pluck('kingdom_id')->map(static fn ($id): string => (string) $id)->all()));
        $definitions = $adapters->definitions();
        $adapterLabels = [];
        foreach ($definitions as $definition) {
            $adapterLabels[$definition['key']] = $definition['label'];
        }

        return Inertia::render('Intelligence/KingdomWatch/Reports', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $allianceKingdom->number],
            'adapters' => $definitions,
            'subscriptions' => $subscriptions->map(fn (KingdomIngestionSubscription $subscription): array => $this->subscriptionRow(
                $subscription,
                $alliance->kingdomId,
                $kingdomRefs[(string) $subscription->kingdom_id] ?? null,
                $adapterLabels[$subscription->adapter_key] ?? $subscription->adapter_key,
            ))->values()->all(),
            'candidates' => $ingestion->recentCandidatesForAlliance($alliance->allianceId)
                ->map(fn (KingdomIngestionCandidate $candidate): array => $this->candidateRow($candidate))->values()->all(),
        ]);
    }

    public function store(Request $request, AllianceContext $context, CreateKingdomIngestionSubscription $create): RedirectResponse
    {
        /** @var array{adapter_key:string} $validated */
        $validated = $request->validate(['adapter_key' => ['required', 'string', 'max:80']]);
        $scope = $context->scope();
        $create->handle($scope->allianceId, $scope->playerId, $validated['adapter_key']);

        return back()->with('actionReceipt', $this->receipt('kingdom-ingestion-subscription-created'));
    }

    public function transition(Request $request, AllianceContext $context, TransitionKingdomIngestionSubscription $transition, string $subscription): RedirectResponse
    {
        /** @var array{state:string} $validated */
        $validated = $request->validate(['state' => ['required', Rule::enum(KingdomIngestionSubscriptionState::class)]]);
        $scope = $context->scope();
        $transition->handle($scope->allianceId, $scope->playerId, $subscription, KingdomIngestionSubscriptionState::from($validated['state']));

        return back()->with('actionReceipt', $this->receipt('kingdom-ingestion-subscription-updated'));
    }

    public function rejectCandidate(Request $request, AllianceContext $context, RejectKingdomIngestionCandidate $reject, string $subscription, string $candidate): RedirectResponse
    {
        $scope = $context->scope();
        $reject->handle($scope->allianceId, $scope->playerId, $subscription, $candidate);

        return back()->with('actionReceipt', $this->receipt('kingdom-ingestion-candidate-rejected'));
    }

    public function replayCandidate(Request $request, AllianceContext $context, ReplayKingdomIngestionCandidate $replay, string $subscription, string $candidate): RedirectResponse
    {
        $scope = $context->scope();
        $replay->handle($scope->allianceId, $scope->playerId, $subscription, $candidate);

        return back()->with('actionReceipt', $this->receipt('kingdom-ingestion-candidate-replayed'));
    }

    /** @return array<string,mixed> */
    private function subscriptionRow(KingdomIngestionSubscription $subscription, string $currentKingdomId, ?KingdomReference $kingdom, string $adapterLabel): array
    {
        /** @var KingdomIngestionBatch|null $latest */
        $latest = $subscription->latestBatch;

        return [
            'id' => (string) $subscription->id,
            'adapterKey' => $subscription->adapter_key,
            'adapterVersion' => $subscription->adapter_version,
            'adapterLabel' => $adapterLabel,
            'state' => $subscription->state->value,
            'kingdom' => $kingdom === null ? null : (string) $kingdom->number,
            'contextCurrent' => $currentKingdomId === (string) $subscription->kingdom_id,
            'sourceCursor' => $subscription->source_cursor,
            'nextRunAt' => $subscription->next_run_at?->toIso8601String(),
            'lastClaimedAt' => $subscription->last_claimed_at?->toIso8601String(),
            'lastSucceededAt' => $subscription->last_succeeded_at?->toIso8601String(),
            'lastFailedAt' => $subscription->last_failed_at?->toIso8601String(),
            'consecutiveFailures' => $subscription->consecutive_failures,
            'circuitOpenUntil' => $subscription->circuit_open_until?->toIso8601String(),
            'lastFailureCode' => $subscription->last_failure_code,
            'blockedAt' => $subscription->blocked_at?->toIso8601String(),
            'blockedReason' => $subscription->blocked_reason,
            'pendingCandidates' => (int) $subscription->getAttribute('pending_candidates_count'),
            'quarantinedCandidates' => (int) $subscription->getAttribute('quarantined_candidates_count'),
            'rejectedCandidates' => (int) $subscription->getAttribute('rejected_candidates_count'),
            'latestBatch' => $latest === null ? null : [
                'id' => (string) $latest->id,
                'state' => $latest->state->value,
                'startedAt' => $latest->started_at->toIso8601String(),
                'completedAt' => $latest->completed_at?->toIso8601String(),
                'recordsReceived' => $latest->records_received,
                'recordsStaged' => $latest->records_staged,
                'recordsQuarantined' => $latest->records_quarantined,
                'recordsRejected' => $latest->records_rejected,
                'failureCode' => $latest->failure_code,
                'nextSourceCursor' => $latest->next_source_cursor,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function candidateRow(KingdomIngestionCandidate $candidate): array
    {
        return [
            'id' => (string) $candidate->id,
            'subscriptionId' => (string) $candidate->subscription_id,
            'adapterKey' => $candidate->subscription->adapter_key,
            'targetKind' => $candidate->target_kind->value,
            'stableGameId' => $candidate->stable_game_id,
            'sourceRecordId' => $candidate->source_record_id,
            'capturedAt' => $candidate->captured_at->toIso8601String(),
            'state' => $candidate->state->value,
            'quarantineCode' => $candidate->quarantine_code,
            'rejectionCode' => $candidate->rejection_code,
        ];
    }
}
