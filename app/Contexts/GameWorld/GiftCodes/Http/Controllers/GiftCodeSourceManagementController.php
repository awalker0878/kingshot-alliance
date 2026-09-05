<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordRegisteredGiftCodeEvidence;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeIngestionHealthQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeResearchedSourceCatalogue;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class GiftCodeSourceManagementController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly GiftCodeIngestionHealthQuery $ingestionHealth,
        private readonly GiftCodeSourceAdapterRegistry $sourceAdapters,
        private readonly GiftCodeResearchedSourceCatalogue $researchedSources,
        private readonly PlatformAuthorization $platformAuthorization,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->account($request);

        return Inertia::render('Platform/GiftCodes/Sources', [
            'user' => ['name' => $actor->name, 'email' => $actor->email],
            'sources' => $this->ingestionHealth->get(100),
            'adapterKeys' => $this->sourceAdapters->keys(),
            'researchedSources' => $this->researchedSources->all(),
            'canManagePlatformPolicy' => $this->platformAuthorization->allows($actor),
        ]);
    }

    public function store(Request $request, ManageGiftCodeSourceRegistry $sources): RedirectResponse
    {
        $adapterKeys = $this->sourceAdapters->keys();
        /** @var array<string,mixed> $validated */
        $validated = $request->validate([
            'source_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]{2,119}$/'],
            'name' => ['required', 'string', 'max:160'],
            'classification' => ['required', Rule::in(['official', 'independent'])],
            'canonical_domain' => ['required', 'string', 'max:255'],
            'verification_method' => ['required', 'string', 'max:80'],
            'adapter_key' => ['nullable', 'string', Rule::in($adapterKeys)],
            'ingestion_enabled' => ['required', 'boolean'],
            'provenance_policy' => ['nullable', 'array'],
            'provenance_policy.auto_verify' => ['nullable', 'boolean'],
            'provenance_policy.feed_path' => ['nullable', 'string', 'max:2048'],
            'provenance_policy.provider_contract_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.structured_contract_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.provider_permission_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.gift_code_category' => ['nullable', 'string', 'max:120'],
            'provenance_policy.x_user_id' => ['nullable', 'string', 'max:32'],
            'provenance_policy.x_username' => ['nullable', 'string', 'max:30'],
            'provenance_policy.platform_permission_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.platform_api_access_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.message_content_access_confirmed' => ['nullable', 'boolean'],
            'provenance_policy.discord_guild_id' => ['nullable', 'string', 'max:32'],
            'provenance_policy.discord_channel_id' => ['nullable', 'string', 'max:32'],
            'provenance_policy.discord_author_ids' => ['nullable', 'array', 'max:50'],
            'provenance_policy.discord_author_ids.*' => ['required', 'string', 'max:32'],
            'provenance_policy.youtube_channel_id' => ['nullable', 'string', 'max:80'],
            'provenance_policy.youtube_channel_title' => ['nullable', 'string', 'max:200'],
            'provenance_policy.reddit_subreddit' => ['nullable', 'string', 'max:32'],
            'provenance_policy.facebook_page_id' => ['nullable', 'string', 'max:64'],
            'provenance_policy.facebook_page_name' => ['nullable', 'string', 'max:200'],
            'provenance_policy.instagram_user_id' => ['nullable', 'string', 'max:64'],
            'provenance_policy.instagram_username' => ['nullable', 'string', 'max:80'],
            'provenance_policy.manual_evidence_allowed' => ['nullable', 'boolean'],
        ]);

        $sourceId = $sources->register($this->account($request), [
            'source_key' => (string) $validated['source_key'],
            'name' => (string) $validated['name'],
            'classification' => (string) $validated['classification'],
            'canonical_domain' => (string) $validated['canonical_domain'],
            'verification_method' => (string) $validated['verification_method'],
            'adapter_key' => isset($validated['adapter_key']) ? (string) $validated['adapter_key'] : null,
            'provenance_policy' => is_array($validated['provenance_policy'] ?? null)
                ? $validated['provenance_policy']
                : [],
            'ingestion_enabled' => (bool) $validated['ingestion_enabled'],
        ]);

        return back()->with('actionReceipt', $this->receipt('gift-code-source-saved', ['source_id' => $sourceId]));
    }

    public function evidence(Request $request, RecordRegisteredGiftCodeEvidence $record): RedirectResponse
    {
        /** @var array<string,mixed> $validated */
        $validated = $request->validate([
            'source_id' => ['required', 'string', 'ulid'],
            'code' => ['required', 'string', 'max:64'],
            'assertion' => ['required', Rule::in(['available', 'invalid', 'expires', 'reward', 'applicability'])],
            'source_url' => ['required', 'url:https', 'max:2048'],
            'assertion_payload' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
            'expiry_precision' => ['nullable', Rule::in(['instant', 'minute', 'hour', 'day'])],
            'expiry_timezone' => ['nullable', 'string', 'max:80'],
            'published_at' => ['nullable', 'date'],
        ]);
        $result = $record->handle($this->account($request), [
            'source_id' => (string) $validated['source_id'],
            'code' => (string) $validated['code'],
            'assertion' => (string) $validated['assertion'],
            'source_url' => (string) $validated['source_url'],
            'assertion_payload' => is_array($validated['assertion_payload'] ?? null)
                ? $validated['assertion_payload']
                : null,
            'expires_at' => isset($validated['expires_at']) ? (string) $validated['expires_at'] : null,
            'expiry_precision' => isset($validated['expiry_precision']) ? (string) $validated['expiry_precision'] : null,
            'expiry_timezone' => isset($validated['expiry_timezone']) ? (string) $validated['expiry_timezone'] : null,
            'published_at' => isset($validated['published_at']) ? (string) $validated['published_at'] : null,
        ]);

        return back()->with('actionReceipt', $this->receipt('gift-code-registered-evidence-recorded', [
            'gift_code_id' => $result['gift_code_id'],
            'provenance_id' => $result['provenance_id'],
        ]));
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
