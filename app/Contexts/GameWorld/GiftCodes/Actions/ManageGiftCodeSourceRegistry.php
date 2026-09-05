<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceReconciliationJob;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ManageGiftCodeSourceRegistry
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private GiftCodeSourceAdapterRegistry $adapters,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    /**
     * @param  array{source_key:string,name:string,classification:string,canonical_domain:string,verification_method:string,adapter_key?:string|null,provenance_policy?:array<string,mixed>|null,ingestion_enabled?:bool}  $attributes
     */
    public function register(AccountIdentity $actor, array $attributes): string
    {
        $this->authorize($actor);
        $sourceKey = trim($attributes['source_key']);
        $domain = mb_strtolower(rtrim(trim($attributes['canonical_domain']), '.'));
        $classification = trim($attributes['classification']);
        $adapterKey = isset($attributes['adapter_key']) ? trim((string) $attributes['adapter_key']) : null;
        $adapterKey = $adapterKey === '' ? null : $adapterKey;
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,119}$/D', $sourceKey) !== 1) {
            throw ValidationException::withMessages(['source_key' => 'Use a stable lowercase source key.']);
        }
        if (! in_array($classification, ['official', 'independent'], true)) {
            throw ValidationException::withMessages(['classification' => 'Choose official or independent source authority.']);
        }
        if ($domain === '' || str_contains($domain, '/') || filter_var('https://'.$domain, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['canonical_domain' => 'Provide a canonical domain without a path.']);
        }
        $ingestionEnabled = (bool) ($attributes['ingestion_enabled'] ?? false);
        if ($ingestionEnabled && $adapterKey === null) {
            throw ValidationException::withMessages(['adapter_key' => 'Enabled ingestion requires a registered adapter key.']);
        }
        if ($ingestionEnabled && $this->adapters->find($adapterKey) === null) {
            throw ValidationException::withMessages(['adapter_key' => 'Enabled ingestion requires an installed source adapter.']);
        }
        $policy = $attributes['provenance_policy'] ?? null;
        $documentAdapterKeys = [
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY,
        ];
        if ($adapterKey !== null && in_array($adapterKey, $documentAdapterKeys, true)) {
            if (filter_var($domain, FILTER_VALIDATE_IP) !== false || $domain === 'localhost') {
                throw ValidationException::withMessages([
                    'canonical_domain' => 'The selected source adapter requires a public canonical hostname.',
                ]);
            }
            $feedPath = is_array($policy) && is_string($policy['feed_path'] ?? null)
                ? trim($policy['feed_path'])
                : '';
            $parts = $feedPath === '' ? false : parse_url($feedPath);
            if ($feedPath === ''
                || ! str_starts_with($feedPath, '/')
                || str_starts_with($feedPath, '//')
                || $parts === false
                || ($parts['path'] ?? null) !== $feedPath
                || str_contains('/'.$feedPath.'/', '/../')
                || str_contains('/'.$feedPath.'/', '/./')) {
                throw ValidationException::withMessages([
                    'feed_path' => 'The selected source adapter requires an absolute source path without a host, query, fragment, or traversal segment.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $actor,
            $attributes,
            $sourceKey,
            $domain,
            $classification,
            $adapterKey,
            $ingestionEnabled,
            $policy,
        ): string {
            $source = GiftCodeSourceRegistry::query()->where('source_key', $sourceKey)->lockForUpdate()->first();
            $existing = $source instanceof GiftCodeSourceRegistry;
            $source ??= new GiftCodeSourceRegistry(['source_key' => $sourceKey, 'policy_revision' => 0]);
            $source->forceFill([
                'name' => trim($attributes['name']),
                'classification' => $classification,
                'canonical_domain' => $domain,
                'is_active' => true,
                'verification_method' => trim($attributes['verification_method']),
                'adapter_key' => $adapterKey,
                'provenance_policy' => $policy,
                'ingestion_enabled' => $ingestionEnabled,
                'revoked_at' => null,
                'created_by_user_id' => $source->created_by_user_id ?? $actor->userId,
                'policy_revision' => $source->policy_revision + 1,
            ])->save();
            if ($existing) {
                $this->scheduleReconciliation($source, 'source_policy_changed');
            }
            $metadata = [
                'source_id' => (string) $source->id,
                'source_key' => $source->source_key,
                'policy_revision' => $source->policy_revision,
                'ingestion_enabled' => $source->ingestion_enabled,
            ];
            $this->audit->record('game_world.gift_code_source.registered', $actor, $source, null, $metadata);
            $this->outbox->record(
                'gift_code.source_changed',
                null,
                $source,
                $metadata,
                'gift-code-source:'.$source->id.':revision:'.$source->policy_revision,
                'gift-code-source:'.$source->id,
            );

            return (string) $source->id;
        });
    }

    public function revoke(AccountIdentity $actor, string $sourceId, string $reason): string
    {
        $this->authorize($actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A source revocation reason is required.']);
        }

        return DB::transaction(function () use ($actor, $sourceId, $reason): string {
            $source = GiftCodeSourceRegistry::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail();
            if ($source->revoked_at === null) {
                $source->forceFill([
                    'is_active' => false,
                    'ingestion_enabled' => false,
                    'revoked_at' => now(),
                    'policy_revision' => $source->policy_revision + 1,
                ])->save();
                $this->scheduleReconciliation($source, 'source_revoked');
                $metadata = [
                    'source_id' => (string) $source->id,
                    'source_key' => $source->source_key,
                    'policy_revision' => $source->policy_revision,
                    'reason' => $reason,
                ];
                $this->audit->record('game_world.gift_code_source.revoked', $actor, $source, null, $metadata);
                $this->outbox->record(
                    'gift_code.source_changed',
                    null,
                    $source,
                    $metadata,
                    'gift-code-source:'.$source->id.':revision:'.$source->policy_revision,
                    'gift-code-source:'.$source->id,
                );
            }

            return (string) $source->id;
        });
    }

    private function scheduleReconciliation(GiftCodeSourceRegistry $source, string $reasonCode): void
    {
        GiftCodeSourceReconciliationJob::query()->firstOrCreate([
            'gift_code_source_id' => (string) $source->id,
            'source_revision' => $source->policy_revision,
        ], ['reason_code' => $reasonCode]);
    }

    private function authorize(AccountIdentity $actor): void
    {
        if (! $actor->emailVerified
            || ! $actor->multiFactorConfirmed
            || ! $this->platformAuthorization->allows($actor)) {
            throw new AuthorizationException('MFA-protected Platform Administrator access is required.');
        }
    }
}
