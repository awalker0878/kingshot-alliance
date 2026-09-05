<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\InstagramMediaGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceReconciliationJob;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\EvaluateGiftCodeSourceActivationReadiness;
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
        private EvaluateGiftCodeSourceActivationReadiness $readiness,
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
        if ($policy !== null && ! is_array($policy)) {
            throw ValidationException::withMessages(['provenance_policy' => 'Source policy must be an object.']);
        }
        $policy ??= [];

        $this->validateFeedPathAdapter($adapterKey, $domain, $policy);
        $this->validateAdapterPolicy($adapterKey, $domain, $classification, $policy, $ingestionEnabled);
        if (($policy['manual_evidence_allowed'] ?? false) === true && ($policy['auto_verify'] ?? false) === true) {
            throw ValidationException::withMessages([
                'auto_verify' => 'Manually recorded registered-source evidence requires explicit curator verification and cannot auto-verify.',
            ]);
        }

        if ($ingestionEnabled) {
            $candidate = new GiftCodeSourceRegistry;
            $candidate->forceFill([
                'source_key' => $sourceKey,
                'name' => trim($attributes['name']),
                'classification' => $classification,
                'canonical_domain' => $domain,
                'is_active' => true,
                'verification_method' => trim($attributes['verification_method']),
                'adapter_key' => $adapterKey,
                'provenance_policy' => $policy,
                'ingestion_enabled' => true,
                'revoked_at' => null,
            ]);
            $activation = $this->readiness->forSource($candidate);
            if (! $activation->ready()) {
                $failed = [];
                foreach ($activation->checks as $key => $check) {
                    if (! $check['ready']) {
                        $failed[] = $key.': '.$check['message'];
                    }
                }
                throw ValidationException::withMessages([
                    'ingestion_enabled' => 'Source activation is not ready: '.implode(' ', $failed),
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
                'activation_status' => $ingestionEnabled ? 'enabled' : ($adapterKey === null ? 'registered' : 'configured'),
                'health_status' => $ingestionEnabled ? ($source->health_status === 'healthy' ? 'healthy' : 'pending') : 'disabled',
                'next_eligible_ingestion_at' => $ingestionEnabled ? $source->next_eligible_ingestion_at : null,
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
                'activation_status' => $source->activation_status,
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
                    'activation_status' => 'revoked',
                    'health_status' => 'disabled',
                    'next_eligible_ingestion_at' => null,
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

    /** @param array<string,mixed> $policy */
    private function validateFeedPathAdapter(?string $adapterKey, string $domain, array $policy): void
    {
        $feedPathAdapterKeys = [
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY,
        ];
        if ($adapterKey === null || ! in_array($adapterKey, $feedPathAdapterKeys, true)) {
            return;
        }
        if (filter_var($domain, FILTER_VALIDATE_IP) !== false || $domain === 'localhost') {
            throw ValidationException::withMessages([
                'canonical_domain' => 'The selected source adapter requires a public canonical hostname.',
            ]);
        }
        $feedPath = is_string($policy['feed_path'] ?? null) ? trim($policy['feed_path']) : '';
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

    /** @param array<string,mixed> $policy */
    private function validateAdapterPolicy(
        ?string $adapterKey,
        string $domain,
        string $classification,
        array $policy,
        bool $ingestionEnabled,
    ): void {
        if ($adapterKey === OfficialXGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'x.com', 'The official X adapter requires x.com as the canonical source domain.');
            $this->requirePattern($policy, 'x_user_id', '/^[0-9]{1,32}$/D', 'The official X adapter requires the confirmed numeric X user id.');
            $this->requirePattern($policy, 'x_username', '/^[A-Za-z0-9_]{1,30}$/D', 'The official X adapter requires the confirmed X username.');
            if ($ingestionEnabled && ($policy['platform_api_access_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['platform_api_access_confirmed' => 'Enable the official X adapter only after API access is confirmed.']);
            }
            $this->requireConfiguredWhenEnabled($ingestionEnabled, 'game_world.gift_codes.x_bearer_token', 'adapter_key', 'Enable the official X adapter only after configuring the X API bearer token.');

            return;
        }

        if ($adapterKey === CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'centurygames.com', 'The Century Games Kingshot news adapter requires centurygames.com as the canonical source domain.');
            if ($ingestionEnabled && ($policy['provider_permission_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'provider_permission_confirmed' => 'Enable Century Games Kingshot news ingestion only after provider permission is confirmed.',
                ]);
            }
            $category = is_string($policy['gift_code_category'] ?? null) ? trim($policy['gift_code_category']) : '';
            if ($category === '' || mb_strlen($category) > 120) {
                throw ValidationException::withMessages([
                    'gift_code_category' => 'The Century Games Kingshot news adapter requires the agreed Gift Code feed category.',
                ]);
            }

            return;
        }

        if (in_array($adapterKey, [JsonFeedGiftCodeSourceAdapter::KEY, RssAtomGiftCodeSourceAdapter::KEY], true)) {
            if ($ingestionEnabled && ($policy['provider_contract_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'provider_contract_confirmed' => 'Enable a generic structured feed only after the provider has established a documented machine-readable contract.',
                ]);
            }

            return;
        }

        if ($adapterKey === StructuredHtmlGiftCodeSourceAdapter::KEY) {
            if ($ingestionEnabled && ($policy['structured_contract_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'structured_contract_confirmed' => 'Enable structured HTML only when the publisher documents that exact machine-readable contract; prose scraping is not supported.',
                ]);
            }

            return;
        }

        if ($adapterKey === DiscordChannelGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'discord.com', 'The Discord channel adapter requires discord.com as the canonical source domain.');
            $this->requirePattern($policy, 'discord_guild_id', '/^[0-9]{1,32}$/D', 'The Discord channel adapter requires the approved guild id.');
            $this->requirePattern($policy, 'discord_channel_id', '/^[0-9]{1,32}$/D', 'The Discord channel adapter requires the approved channel id.');
            $authors = $policy['discord_author_ids'] ?? null;
            if (! is_array($authors) || $authors === [] || count($authors) > 50) {
                throw ValidationException::withMessages(['discord_author_ids' => 'Provide one to fifty approved Discord author ids.']);
            }
            foreach ($authors as $authorId) {
                if (! is_string($authorId) || preg_match('/^[0-9]{1,32}$/D', trim($authorId)) !== 1) {
                    throw ValidationException::withMessages(['discord_author_ids' => 'Discord author ids must be numeric snowflakes.']);
                }
            }
            if ($ingestionEnabled && (($policy['platform_permission_confirmed'] ?? false) !== true
                || ($policy['message_content_access_confirmed'] ?? false) !== true)) {
                throw ValidationException::withMessages([
                    'platform_permission_confirmed' => 'Enable Discord ingestion only after bot installation, channel scope, and message-content access are confirmed.',
                ]);
            }
            $this->requireConfiguredWhenEnabled($ingestionEnabled, 'game_world.gift_codes.discord_bot_token', 'adapter_key', 'Enable Discord ingestion only after configuring the bot token.');

            return;
        }

        if ($adapterKey === YouTubeChannelGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'youtube.com', 'The YouTube channel adapter requires youtube.com as the canonical source domain.');
            $this->requirePattern($policy, 'youtube_channel_id', '/^UC[A-Za-z0-9_-]{20,40}$/D', 'The YouTube channel adapter requires the confirmed channel id.');
            $this->requireNonEmpty($policy, 'youtube_channel_title', 200, 'The YouTube channel adapter requires the confirmed channel title.');
            if ($ingestionEnabled && ($policy['platform_api_access_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['platform_api_access_confirmed' => 'Enable YouTube ingestion only after Data API access is confirmed.']);
            }
            $this->requireConfiguredWhenEnabled($ingestionEnabled, 'game_world.gift_codes.youtube_api_key', 'adapter_key', 'Enable YouTube ingestion only after configuring the Data API key.');

            return;
        }

        if ($adapterKey === RedditSubredditGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'reddit.com', 'The Reddit adapter requires reddit.com as the canonical source domain.');
            if ($classification !== 'independent') {
                throw ValidationException::withMessages(['classification' => 'Reddit discovery must remain an independent source.']);
            }
            if (($policy['auto_verify'] ?? false) === true) {
                throw ValidationException::withMessages(['auto_verify' => 'Reddit discovery cannot automatically verify Gift Code evidence.']);
            }
            $this->requirePattern($policy, 'reddit_subreddit', '/^[A-Za-z0-9_]{2,32}$/D', 'The Reddit adapter requires a valid subreddit name.');
            if ($ingestionEnabled && ($policy['platform_api_access_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['platform_api_access_confirmed' => 'Enable Reddit ingestion only after Data API registration and access are confirmed.']);
            }
            foreach ([
                'game_world.gift_codes.reddit_client_id',
                'game_world.gift_codes.reddit_client_secret',
                'game_world.gift_codes.reddit_user_agent',
            ] as $configKey) {
                $this->requireConfiguredWhenEnabled($ingestionEnabled, $configKey, 'adapter_key', 'Enable Reddit ingestion only after configuring OAuth credentials and a descriptive User-Agent.');
            }

            return;
        }

        if ($adapterKey === FacebookPageGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'facebook.com', 'The Facebook Page adapter requires facebook.com as the canonical source domain.');
            $this->requirePattern($policy, 'facebook_page_id', '/^[0-9]{1,64}$/D', 'The Facebook Page adapter requires the confirmed numeric Page id.');
            $this->requireNonEmpty($policy, 'facebook_page_name', 200, 'The Facebook Page adapter requires the confirmed Page name.');
            if ($ingestionEnabled && ($policy['platform_permission_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['platform_permission_confirmed' => 'Enable Facebook ingestion only after Page access and platform permission are confirmed.']);
            }
            $this->requireConfiguredWhenEnabled($ingestionEnabled, 'game_world.gift_codes.facebook_access_token', 'adapter_key', 'Enable Facebook ingestion only after configuring a Page access token.');

            return;
        }

        if ($adapterKey === InstagramMediaGiftCodeSourceAdapter::KEY) {
            $this->requireDomain($domain, 'instagram.com', 'The Instagram media adapter requires instagram.com as the canonical source domain.');
            $this->requirePattern($policy, 'instagram_user_id', '/^[0-9]{1,64}$/D', 'The Instagram media adapter requires the confirmed professional account id.');
            $this->requirePattern($policy, 'instagram_username', '/^[A-Za-z0-9._]{1,80}$/D', 'The Instagram media adapter requires the confirmed username.');
            if ($ingestionEnabled && ($policy['platform_permission_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['platform_permission_confirmed' => 'Enable Instagram ingestion only after professional-account API access is confirmed.']);
            }
            $this->requireConfiguredWhenEnabled($ingestionEnabled, 'game_world.gift_codes.instagram_access_token', 'adapter_key', 'Enable Instagram ingestion only after configuring an account access token.');
        }
    }

    private function requireDomain(string $actual, string $expected, string $message): void
    {
        if ($actual !== $expected) {
            throw ValidationException::withMessages(['canonical_domain' => $message]);
        }
    }

    /** @param array<string,mixed> $policy */
    private function requirePattern(array $policy, string $key, string $pattern, string $message): void
    {
        $value = is_string($policy[$key] ?? null) ? trim($policy[$key]) : '';
        if (preg_match($pattern, $value) !== 1) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    /** @param array<string,mixed> $policy */
    private function requireNonEmpty(array $policy, string $key, int $maximum, string $message): void
    {
        $value = is_string($policy[$key] ?? null) ? trim($policy[$key]) : '';
        if ($value === '' || mb_strlen($value) > $maximum) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    private function requireConfiguredWhenEnabled(
        bool $ingestionEnabled,
        string $configKey,
        string $field,
        string $message,
    ): void {
        if ($ingestionEnabled && trim((string) config($configKey, '')) === '') {
            throw ValidationException::withMessages([$field => $message]);
        }
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
