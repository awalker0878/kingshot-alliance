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
                'transports' => ['x_api_v2'],
                'candidate_adapter_keys' => [OfficialXGiftCodeSourceAdapter::KEY],
                'gate' => 'administrator_approval_confirmed_account_identity_and_x_credentials',
                'notes' => 'Uses the documented X user-post timeline and accepts only explicitly labelled Gift Code lines.',
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
                'gate' => 'provider_permission_and_explicit_feed_contract',
                'notes' => 'Permission-gated provider parser; it does not scrape Century Games web-page prose.',
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
                'transports' => ['legitimate_discord_bot'],
                'candidate_adapter_keys' => [DiscordChannelGiftCodeSourceAdapter::KEY],
                'gate' => 'bot_installation_channel_scope_author_allowlist_and_platform_terms',
                'notes' => 'Uses an installed bot with approved guild/channel scope and author allowlisting. Self-bots and user-token automation remain excluded.',
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
                'transports' => ['facebook_graph_api', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [FacebookPageGiftCodeSourceAdapter::KEY],
                'gate' => 'page_access_platform_permission_and_account_identity',
                'notes' => 'Documented Graph API adapter is available only after Page access and platform permission are confirmed. The adapter does not grant that permission.',
            ],
            [
                'source_key' => 'kingshot-instagram',
                'name' => 'Kingshot Instagram',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'instagram.com',
                'transports' => ['instagram_graph_api', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [InstagramMediaGiftCodeSourceAdapter::KEY],
                'gate' => 'professional_account_access_platform_permission_and_identity',
                'notes' => 'Documented professional-account API adapter is available only after the account has authorized the integration. Consumer-account scraping remains excluded.',
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
                'notes' => 'Discovery-only adapter for a registered Data API app. Automatic verification is forbidden and the integration can be disabled if Reddit access is unavailable during the Developer Platform transition.',
            ],
            [
                'source_key' => 'kingshot-youtube',
                'name' => 'Kingshot YouTube',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'youtube.com',
                'transports' => ['youtube_data_api', 'registered_manual_evidence'],
                'candidate_adapter_keys' => [YouTubeChannelGiftCodeSourceAdapter::KEY],
                'gate' => 'data_api_access_and_confirmed_channel_identity',
                'notes' => 'Uses channels.list plus the channel uploads playlist through playlistItems.list; it does not rely on search indexing or scrape video pages.',
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
