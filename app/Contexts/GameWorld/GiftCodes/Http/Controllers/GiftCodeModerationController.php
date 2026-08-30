<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeCuratorGrant;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\ModerateGiftCode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeCuratorGrant;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeModerationDecision;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeIngestionHealthQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class GiftCodeModerationController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly GiftCodeIngestionHealthQuery $ingestionHealth,
        private readonly GiftCodeSourceAdapterRegistry $sourceAdapters,
        private readonly PlatformAuthorization $platformAuthorization,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->account($request);
        $queue = (string) $request->query('queue', 'pending');
        $allowedQueues = ['pending', 'disputed', 'conflicting-expiry', 'suspicious-source', 'heavily-reported', 'quarantined', 'ingestion-quarantined', 'source-revocation'];
        if (! in_array($queue, $allowedQueues, true)) {
            $queue = 'pending';
        }

        $query = GiftCode::query()
            ->withCount('provenances')
            ->withCount('redemptions')
            ->withCount([
                'redemptions as negative_redemptions_count' => static fn ($builder) => $builder->whereIn('status', [
                    GiftCodeRedemptionStatus::InvalidCode->value,
                    GiftCodeRedemptionStatus::Expired->value,
                ]),
            ]);

        if ($queue === 'pending') {
            $query->where('status', GiftCodeStatus::Pending->value);
        } elseif ($queue === 'disputed') {
            $query->where('status', GiftCodeStatus::Disputed->value);
        } elseif ($queue === 'conflicting-expiry') {
            $query->where('status_reason_code', 'credible_expiry_conflict');
        } elseif ($queue === 'quarantined') {
            $query->where('status', GiftCodeStatus::Quarantined->value);
        } elseif ($queue === 'ingestion-quarantined') {
            $query->whereHas('provenances', static fn ($builder) => $builder
                ->where('verification_state', GiftCodeEvidenceVerificationState::Quarantined->value));
        } elseif ($queue === 'suspicious-source') {
            $query->whereHas('provenances', static fn ($builder) => $builder
                ->whereNull('registered_source_id')
                ->whereNotNull('source_url'));
        } elseif ($queue === 'heavily-reported') {
            $query->whereHas(
                'redemptions',
                static fn ($builder) => $builder->whereIn('status', [
                    GiftCodeRedemptionStatus::InvalidCode->value,
                    GiftCodeRedemptionStatus::Expired->value,
                ]),
                '>=',
                3,
            );
        } elseif ($queue === 'source-revocation') {
            $query->whereHas('provenances.registeredSource', static fn ($builder) => $builder->whereNotNull('revoked_at'));
        }

        $page = $query
            ->orderByDesc('status_changed_at')
            ->orderByDesc('id')
            ->cursorPaginate(min(100, max(10, (int) config('game_world.gift_codes.catalog_page_size', 25))));

        $selected = null;
        $selectedId = $request->query('gift_code');
        if (is_string($selectedId) && $selectedId !== '') {
            $selected = GiftCode::query()
                ->with(['provenances.registeredSource', 'redemptions', 'moderationDecisions'])
                ->find($selectedId);
        }

        /** @var array<int,array<string,mixed>> $curators */
        $curators = [];
        if ($this->platformAuthorization->allows($actor)) {
            /** @var \Illuminate\Database\Eloquent\Collection<int,GiftCodeCuratorGrant> $grants */
            $grants = GiftCodeCuratorGrant::query()
                ->join('users', 'users.id', '=', 'gift_code_curator_grants.user_id')
                ->whereNull('gift_code_curator_grants.revoked_at')
                ->orderBy('users.email')
                ->get([
                    'gift_code_curator_grants.id',
                    'gift_code_curator_grants.user_id',
                    'gift_code_curator_grants.granted_at',
                    'users.name as user_name',
                    'users.email as user_email',
                ]);
            foreach ($grants as $grant) {
                $curators[] = [
                    'id' => (string) $grant->id,
                    'userId' => (int) $grant->user_id,
                    'name' => (string) $grant->getAttribute('user_name'),
                    'email' => (string) $grant->getAttribute('user_email'),
                    'grantedAt' => $grant->granted_at->toIso8601String(),
                ];
            }
        }

        $items = [];
        foreach ($page->items() as $giftCode) {
            if (! $giftCode instanceof GiftCode) {
                continue;
            }
            $items[] = [
                'id' => (string) $giftCode->id,
                'code' => $giftCode->code,
                'status' => $giftCode->status->value,
                'reasonCode' => $giftCode->status_reason_code,
                'statusRevision' => $giftCode->status_revision,
                'expiresAt' => $giftCode->expires_at?->toIso8601String(),
                'provenanceCount' => (int) $giftCode->provenances_count,
                'redemptionCount' => (int) $giftCode->redemptions_count,
                'negativeRedemptionCount' => (int) $giftCode->getAttribute('negative_redemptions_count'),
            ];
        }

        return Inertia::render('Platform/GiftCodes/Review', [
            'user' => ['name' => $actor->name, 'email' => $actor->email],
            'queue' => $queue,
            'queues' => $allowedQueues,
            'items' => $items,
            'nextCursor' => $page->nextCursor()?->encode(),
            'previousCursor' => $page->previousCursor()?->encode(),
            'selected' => $selected instanceof GiftCode ? $this->detail($selected) : null,
            'bulkPreview' => $request->session()->get('giftCodeBulkReviewPreview'),
            'bulkResult' => $request->session()->get('giftCodeBulkReviewResult'),
            'ingestionHealth' => $this->ingestionHealth->get(),
            'canManagePlatformPolicy' => $this->platformAuthorization->allows($actor),
            'adapterKeys' => $this->sourceAdapters->keys(),
            'curators' => $curators,
        ]);
    }

    public function storeSource(Request $request, ManageGiftCodeSourceRegistry $sources): RedirectResponse
    {
        $adapterKeys = $this->sourceAdapters->keys();
        /** @var array{source_key:string,name:string,classification:string,canonical_domain:string,verification_method:string,adapter_key?:string|null,auto_verify:bool,ingestion_enabled:bool} $validated */
        $validated = $request->validate([
            'source_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]{2,119}$/'],
            'name' => ['required', 'string', 'max:160'],
            'classification' => ['required', Rule::in(['official', 'independent'])],
            'canonical_domain' => ['required', 'string', 'max:255'],
            'verification_method' => ['required', 'string', 'max:80'],
            'adapter_key' => ['nullable', 'string', Rule::in($adapterKeys)],
            'auto_verify' => ['required', 'boolean'],
            'ingestion_enabled' => ['required', 'boolean'],
        ]);
        $sourceId = $sources->register($this->account($request), [
            'source_key' => $validated['source_key'],
            'name' => $validated['name'],
            'classification' => $validated['classification'],
            'canonical_domain' => $validated['canonical_domain'],
            'verification_method' => $validated['verification_method'],
            'adapter_key' => $validated['adapter_key'] ?? null,
            'provenance_policy' => ['auto_verify' => $validated['auto_verify']],
            'ingestion_enabled' => $validated['ingestion_enabled'],
        ]);

        return back()->with('actionReceipt', $this->receipt('gift-code-source-saved', ['source_id' => $sourceId]));
    }

    public function revokeSource(
        Request $request,
        string $source,
        ManageGiftCodeSourceRegistry $sources,
    ): RedirectResponse {
        /** @var array{reason:string} $validated */
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $sourceId = $sources->revoke($this->account($request), $source, $validated['reason']);

        return back()->with('actionReceipt', $this->receipt('gift-code-source-revoked', ['source_id' => $sourceId]));
    }

    public function grantCurator(Request $request, ManageGiftCodeCuratorGrant $grants): RedirectResponse
    {
        /** @var array{email:string} $validated */
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $targetUserId = $this->accounts->findIdByEmail($validated['email']);
        if ($targetUserId === null) {
            throw ValidationException::withMessages([
                'email' => 'No account exists with that email address.',
            ]);
        }
        $grantId = $grants->grant($this->account($request), $targetUserId);

        return back()->with('actionReceipt', $this->receipt('gift-code-curator-granted', ['grant_id' => $grantId]));
    }

    public function revokeCurator(
        Request $request,
        string $grant,
        ManageGiftCodeCuratorGrant $grants,
    ): RedirectResponse {
        $grantId = $grants->revoke($this->account($request), $grant);

        return back()->with('actionReceipt', $this->receipt('gift-code-curator-revoked', ['grant_id' => $grantId]));
    }

    public function moderate(Request $request, string $giftCode, ModerateGiftCode $moderate): RedirectResponse
    {
        $validated = $this->validatedDecision($request);
        $decisionId = $moderate->handle(
            $this->account($request),
            $giftCode,
            GiftCodeModerationAction::from((string) $validated['action']),
            $validated['reason'] ?? null,
            $validated['evidence_ids'] ?? [],
            isset($validated['proposed_status']) ? GiftCodeStatus::from((string) $validated['proposed_status']) : null,
            $this->decisionMetadata($validated),
        );

        return back()->with('actionReceipt', $this->receipt('gift-code-moderated', ['decision_id' => $decisionId]));
    }

    public function bulk(Request $request, ModerateGiftCode $moderate): RedirectResponse
    {
        /** @var array{gift_code_ids: list<string>, action: string, confirmed: bool, reason?: string|null, proposed_status?: string|null, expires_at?: string|null, expiry_precision?: string|null} $validated */
        $validated = $request->validate([
            'gift_code_ids' => ['required', 'array', 'min:1', 'max:50'],
            'gift_code_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'action' => ['required', Rule::enum(GiftCodeModerationAction::class)],
            'confirmed' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'proposed_status' => ['nullable', Rule::enum(GiftCodeStatus::class)],
            'expires_at' => ['nullable', 'date'],
            'expiry_precision' => ['nullable', 'in:instant,minute,hour,day'],
        ]);

        if (! $validated['confirmed']) {
            $existingIds = GiftCode::query()
                ->whereIn('id', $validated['gift_code_ids'])
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all();

            return back()->with('giftCodeBulkReviewPreview', [
                'action' => $validated['action'],
                'requested' => count($validated['gift_code_ids']),
                'eligible' => count($existingIds),
                'giftCodeIds' => $existingIds,
            ]);
        }

        $actor = $this->account($request);
        $results = [];
        foreach ($validated['gift_code_ids'] as $giftCodeId) {
            try {
                $decisionId = $moderate->handle(
                    $actor,
                    $giftCodeId,
                    GiftCodeModerationAction::from($validated['action']),
                    $validated['reason'] ?? null,
                    [],
                    isset($validated['proposed_status']) ? GiftCodeStatus::from((string) $validated['proposed_status']) : null,
                    $this->decisionMetadata($validated),
                );
                $results[] = ['giftCodeId' => $giftCodeId, 'outcome' => 'succeeded', 'decisionId' => $decisionId];
            } catch (Throwable $exception) {
                report($exception);
                $results[] = ['giftCodeId' => $giftCodeId, 'outcome' => 'failed', 'message' => $exception->getMessage()];
            }
        }

        return back()->with('giftCodeBulkReviewResult', [
            'action' => $validated['action'],
            'items' => $results,
            'succeeded' => count(array_filter($results, static fn (array $item): bool => $item['outcome'] === 'succeeded')),
            'failed' => count(array_filter($results, static fn (array $item): bool => $item['outcome'] === 'failed')),
        ]);
    }

    /** @return array<string, mixed> */
    private function detail(GiftCode $giftCode): array
    {
        $distribution = $giftCode->redemptions
            ->groupBy(static fn (GiftCodeRedemption $redemption): string => $redemption->status->value)
            ->map(static fn ($items): int => $items->count())
            ->all();

        return [
            'id' => (string) $giftCode->id,
            'code' => $giftCode->code,
            'status' => $giftCode->status->value,
            'reasonCode' => $giftCode->status_reason_code,
            'statusRevision' => $giftCode->status_revision,
            'statusEvidenceIds' => $giftCode->status_evidence_ids ?? [],
            'expiresAt' => $giftCode->expires_at?->toIso8601String(),
            'expiresPrecision' => $giftCode->expires_precision,
            'redemptionDistribution' => $distribution,
            'affectedGovernors' => $giftCode->redemptions->map(static fn (GiftCodeRedemption $redemption): array => [
                'playerId' => $redemption->player_id,
                'kingdomId' => $redemption->kingdom_id,
                'status' => $redemption->status->value,
                'attempts' => $redemption->attempts,
            ])->values()->all(),
            'evidence' => $giftCode->provenances->map(static fn (GiftCodeProvenance $evidence): array => [
                'id' => (string) $evidence->id,
                'sourceType' => $evidence->source_type->value,
                'sourceLabel' => $evidence->source_label,
                'sourceUrl' => $evidence->source_url,
                'registeredSourceId' => $evidence->registered_source_id,
                'registeredSourceName' => $evidence->registeredSource?->name,
                'assertion' => $evidence->assertion,
                'verificationState' => $evidence->verification_state->value,
                'classification' => $evidence->evidence_classification->value,
                'claimedExpiresAt' => $evidence->claimed_expires_at?->toIso8601String(),
                'expiryPrecision' => $evidence->expiry_precision,
                'publishedAt' => $evidence->published_at?->toIso8601String(),
                'observedAt' => $evidence->observed_at->toIso8601String(),
                'contentFingerprint' => $evidence->content_fingerprint,
            ])->values()->all(),
            'decisions' => $giftCode->moderationDecisions->map(static fn (GiftCodeModerationDecision $decision): array => [
                'id' => (string) $decision->id,
                'action' => $decision->action->value,
                'reason' => $decision->reason,
                'previousStatus' => $decision->previous_status,
                'proposedStatus' => $decision->proposed_status,
                'evidenceIds' => $decision->evidence_ids ?? [],
                'decidedAt' => $decision->decided_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedDecision(Request $request): array
    {
        return $request->validate([
            'action' => ['required', Rule::enum(GiftCodeModerationAction::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'evidence_ids' => ['nullable', 'array', 'max:100'],
            'evidence_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'proposed_status' => ['nullable', Rule::enum(GiftCodeStatus::class)],
            'expires_at' => ['nullable', 'date'],
            'expiry_precision' => ['nullable', 'in:instant,minute,hour,day'],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function decisionMetadata(array $validated): array
    {
        $metadata = [];
        if (isset($validated['expires_at'])) {
            $metadata['expires_at'] = $validated['expires_at'];
        }
        if (isset($validated['expiry_precision'])) {
            $metadata['expiry_precision'] = $validated['expiry_precision'];
        }

        return $metadata;
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
