<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;

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
                'transports' => ['structured_feed_only'],
                'candidate_adapter_keys' => [
                    JsonFeedGiftCodeSourceAdapter::KEY,
                    RssAtomGiftCodeSourceAdapter::KEY,
                    StructuredHtmlGiftCodeSourceAdapter::KEY,
                ],
                'gate' => 'structured_feed_or_machine_readable_contract_and_authority_review',
                'notes' => 'Do not infer Gift Codes from wiki prose. Use only a legitimate structured feed or explicit machine-readable publication contract.',
            ],
            [
                'source_key' => 'kingshot-official-discord',
                'name' => 'Kingshot official Discord',
                'stage' => 2,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'official_candidate',
                'canonical_domain_candidate' => 'discord.com',
                'transports' => ['legitimate_discord_bot'],
                'candidate_adapter_keys' => [],
                'gate' => 'bot_installation_channel_scope_and_platform_terms',
                'notes' => 'Requires a legitimate installed bot and explicit channel permissions. Self-bots and user-token automation are excluded.',
            ],
            [
                'source_key' => 'kingshot-net',
                'name' => 'Kingshot.net',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshot.net',
                'transports' => ['structured_source_if_available'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'Independent corroboration only; one source never satisfies the independent-evidence threshold by itself.',
            ],
            [
                'source_key' => 'kingshot-optimizer',
                'name' => 'Kingshot Optimizer',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshotoptimizer.com',
                'transports' => ['structured_source_if_available'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'Independent corroboration only; no generic page scraping.',
            ],
            [
                'source_key' => 'kingshot-mastery',
                'name' => 'Kingshot Mastery',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'kingshotmastery.com',
                'transports' => ['structured_source_if_available'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'Independent corroboration only; no reverse engineering of its redemption tooling.',
            ],
            [
                'source_key' => 'kingshot-atlas',
                'name' => 'Kingshot Atlas',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => 'ks-atlas.com',
                'transports' => ['structured_source_if_available'],
                'candidate_adapter_keys' => [],
                'gate' => 'independent_source_review_and_corroboration',
                'notes' => 'Independent corroboration only; use a documented/public structured source if one is offered.',
            ],
            [
                'source_key' => 'selected-editorial-sources',
                'name' => 'Selected editorial Gift Code sources',
                'stage' => 3,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'independent_candidate',
                'canonical_domain_candidate' => null,
                'transports' => ['documented_feed_or_manual_evidence'],
                'candidate_adapter_keys' => [],
                'gate' => 'per_publisher_review_and_corroboration',
                'notes' => 'Each publisher must receive its own source registry entry and provenance; this catalogue row is not a shared authority.',
            ],
            [
                'source_key' => 'kingshot-facebook',
                'name' => 'Kingshot Facebook',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'facebook.com',
                'transports' => ['official_platform_api_or_manual_evidence'],
                'candidate_adapter_keys' => [],
                'gate' => 'platform_api_terms_permissions_and_account_identity',
                'notes' => 'Redundancy/discovery only until a documented platform integration is separately approved.',
            ],
            [
                'source_key' => 'kingshot-instagram',
                'name' => 'Kingshot Instagram',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'instagram.com',
                'transports' => ['official_platform_api_or_manual_evidence'],
                'candidate_adapter_keys' => [],
                'gate' => 'platform_api_terms_permissions_and_account_identity',
                'notes' => 'Redundancy/discovery only until a documented platform integration is separately approved.',
            ],
            [
                'source_key' => 'kingshot-reddit',
                'name' => 'Kingshot Reddit',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_discovery',
                'canonical_domain_candidate' => 'reddit.com',
                'transports' => ['official_platform_api_or_manual_evidence'],
                'candidate_adapter_keys' => [],
                'gate' => 'platform_api_terms_and_independent_corroboration',
                'notes' => 'Discovery signal only unless a specific independently reviewed source is registered and corroborated.',
            ],
            [
                'source_key' => 'kingshot-youtube',
                'name' => 'Kingshot YouTube',
                'stage' => 4,
                'catalogue_state' => 'research_only',
                'evidence_role' => 'platform_dependent_redundancy',
                'canonical_domain_candidate' => 'youtube.com',
                'transports' => ['official_platform_api_or_manual_evidence'],
                'candidate_adapter_keys' => [],
                'gate' => 'platform_api_terms_permissions_and_channel_identity',
                'notes' => 'Redundancy/discovery only until a documented platform integration is separately approved.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
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
