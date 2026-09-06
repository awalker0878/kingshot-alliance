<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

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

/**
 * Research catalogue only.
 *
 * Entries in this catalogue are candidates for later administrator review. The
 * catalogue never creates GiftCodeSourceRegistry rows and intentionally has no
 * approved, auto_verify, or ingestion_enabled fields.
 */
final class GiftCodeResearchedSourceCatalogue
{
    /**
     * @return list<array{
     *     source_key:string,
     *     name:string,
     *     stage:int,
     *     catalogue_state:string,
     *     evidence_role:string,
     *     canonical_domain_candidate:string|null,
     *     transports:list<string>,
     *     candidate_adapter_keys:list<string>,
     *     gate:string,
     *     notes:string
     * }>
     */
    public function all(): array
    {
        return [
            [
                'source_key' => 'century-games-cooperative',
                'name' => 'Century Games cooperative Gift Code source',
                'stage' => 0,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'centurygames.com',
                'transports' => ['signed_webhook', 'cooperative_json', 'cooperative_rss'],
                'candidate_adapter_keys' => [
                    JsonFeedGiftCodeSourceAdapter::KEY,
                    RssAtomGiftCodeSourceAdapter::KEY,
                ],
                'gate' => 'provider_cooperation_and_explicit_source_policy',
                'notes' => 'Preferred first-party path. A signed webhook is a push transport, not an adapter or automatic trust grant.',
            ],
            [
                'source_key' => 'kingshot-official-x',
                'name' => 'Kingshot official X account',
                'stage' => 1,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'x.com',
                'transports' => ['x_filtered_stream_webhook', 'x_api_v2_reconciliation'],
                'candidate_adapter_keys' => [OfficialXGiftCodeSourceAdapter::KEY],
                'gate' => 'administrator_approval_confirmed_account_identity_x_credentials_and_realtime_entitlement_for_push',
                'notes' => 'Prefer Filtered Stream webhook delivery when the configured X account has the required entitlement. Timeline polling remains an independent reconciliation/fallback path and accepts only explicit Gift Code evidence.',
            ],
            [
                'source_key' => 'century-games-kingshot-news',
                'name' => 'Century Games Kingshot news feed',
                'stage' => 1,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'centurygames.com',
                'transports' => ['permissioned_rss'],
                'candidate_adapter_keys' => [CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY],
                'gate' => 'express_provider_permission_and_explicit_feed_contract',
                'notes' => 'A public RSS endpoint exists, but that does not establish authorization. Keep ingestion disabled until express permission or a cooperative contract is recorded; the parser does not scrape page prose.',
            ],
            [
                'source_key' => 'kingshot-official-wiki',
                'name' => 'Kingshot Official Wiki',
                'stage' => 2,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'kingshotwiki.com',
                'transports' => ['registered_manual_evidence', 'structured_feed_if_documented'],
                'candidate_adapter_keys' => [
                    JsonFeedGiftCodeSourceAdapter::KEY,
                    RssAtomGiftCodeSourceAdapter::KEY,
                    StructuredHtmlGiftCodeSourceAdapter::KEY,
                ],
                'gate' => 'authority_review_then_manual_evidence_or_documented_structured_contract',
                'notes' => 'No documented machine feed was identified in the 2026-09-05 review. Record exact Wiki evidence manually under its registered source identity unless a legitimate structured contract is later offered; never infer codes from prose automatically.',
            ],
            [
                'source_key' => 'kingshot-official-discord',
                'name' => 'Kingshot official Discord',
                'stage' => 2,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'discord.com',
                'transports' => ['discord_gateway', 'discord_rest_reconciliation'],
                'candidate_adapter_keys' => [DiscordChannelGiftCodeSourceAdapter::KEY],
                'gate' => 'bot_installation_channel_scope_author_allowlist_message_content_access_and_platform_terms',
                'notes' => 'Use Gateway MESSAGE_CREATE for low-latency discovery only after legitimate bot installation and required message-content access. Canonical REST message retrieval/high-water reconciliation covers reconnects and missed events. Self-bots and user-token automation remain excluded.',
            ],
            [
                'source_key' => 'kingshot-net',
                'name' => 'Kingshot.net',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshot.net',
                'transports' => ['registered_manual_evidence', 'structured_source_if_documented'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'No documented machine interface was identified in the 2026-09-05 review. Use registered manual evidence; one publisher never satisfies the independent-evidence threshold by itself.',
            ],
            [
                'source_key' => 'kingshot-optimizer',
                'name' => 'Kingshot Optimizer',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshotoptimizer.com',
                'transports' => ['registered_manual_evidence', 'structured_source_if_documented'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'No documented machine interface was identified in the 2026-09-05 review. Use registered manual evidence; no generic page scraping.',
            ],
            [
                'source_key' => 'kingshot-mastery',
                'name' => 'Kingshot Mastery',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshotmastery.com',
                'transports' => ['registered_manual_evidence', 'structured_source_if_documented'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'No documented machine interface was identified in the 2026-09-05 review. Use registered manual evidence; do not reverse engineer redemption tooling.',
            ],
            [
                'source_key' => 'kingshot-atlas',
                'name' => 'Kingshot Atlas',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'ks-atlas.com',
                'transports' => ['registered_manual_evidence', 'structured_source_if_documented'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'No documented machine interface was identified in the 2026-09-05 review. Use registered manual evidence until a documented/public structured source is offered.',
            ],
            [
                'source_key' => 'selected-editorial-sources',
                'name' => 'Selected editorial Gift Code sources',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => null,
                'transports' => ['registered_manual_evidence', 'documented_feed_if_available'],
                'candidate_adapter_keys' => [],
                'gate' => 'per_publisher_review_and_corroboration',
                'notes' => 'Each publisher must receive its own source registry entry and provenance; this catalogue row is never a shared authority identity.',
            ],
            [
                'source_key' => 'kingshot-facebook',
                'name' => 'Kingshot Facebook',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'facebook.com',
                'transports' => ['facebook_page_webhook', 'facebook_graph_api_reconciliation', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [FacebookPageGiftCodeSourceAdapter::KEY],
                'gate' => 'page_access_webhook_configuration_platform_permission_and_account_identity',
                'notes' => 'When the Meta application has the required Page access, signed Page feed webhook events provide discovery and trigger a canonical Graph fetch. Graph polling remains reconciliation/backfill. The integration never grants its own Page permission.',
            ],
            [
                'source_key' => 'kingshot-instagram',
                'name' => 'Kingshot Instagram',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'instagram.com',
                'transports' => ['instagram_graph_api_polling', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [InstagramMediaGiftCodeSourceAdapter::KEY],
                'gate' => 'professional_account_access_platform_permission_and_identity',
                'notes' => 'Keep this source poll-first until a documented and approved own-media publication webhook suitable for this source is verified. Consumer-account scraping remains excluded.',
            ],
            [
                'source_key' => 'kingshot-reddit',
                'name' => 'Kingshot Reddit',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_discovery',
                'canonical_domain_candidate' => 'reddit.com',
                'transports' => ['reddit_data_api', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [RedditSubredditGiftCodeSourceAdapter::KEY],
                'gate' => 'registered_data_api_access_terms_and_independent_review',
                'notes' => 'Optional discovery-only adapter for a registered Data API app. Automatic verification is forbidden; Reddit platform-transition risk is intentionally isolated so loss of API access cannot degrade the core official-source capability.',
            ],
            [
                'source_key' => 'kingshot-youtube',
                'name' => 'Kingshot YouTube',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'youtube.com',
                'transports' => ['youtube_websub', 'youtube_data_api_reconciliation', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [YouTubeChannelGiftCodeSourceAdapter::KEY],
                'gate' => 'data_api_access_confirmed_channel_identity_and_websub_callback_for_push',
                'notes' => 'Prefer WebSub for discovery. Each notification triggers canonical Data API metadata retrieval before evidence extraction; playlist polling remains reconciliation/backfill rather than the primary freshness cursor.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function forStage(int $stage): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $source): bool => $source['stage'] === $stage,
        ));
    }

    /** @return array<string, mixed>|null */
    public function find(string $sourceKey): ?array
    {
        foreach ($this->all() as $source) {
            if ($source['source_key'] === $sourceKey) {
                return $source;
            }
        }

        return null;
    }
}
