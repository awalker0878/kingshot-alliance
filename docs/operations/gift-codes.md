# Gift Code operations

Status: Current

## Launch configuration

Staging and production must explicitly enable `GIFT_CODES_MODERATION`, `GIFT_CODES_APPROVED_SOURCE_INGESTION`, and `GIFT_CODES_NOTIFICATION_FANOUT`. `production:check` reports a failed Gift Code launch check while any required launch flag is disabled. Disabling a flag remains the immediate rollback control for that slice.

Before approved-source ingestion is launch-ready, a Platform Administrator with verified email, MFA, and recent password confirmation must register at least one active source. Generic source policy remains available from `/platform/gift-codes`; the full researched-source policy surface is `/platform/gift-codes/sources`. Research catalogue entries never become source registry rows automatically.

The generic JSON/RSS/HTML pull adapters retrieve HTTPS content from a registered public canonical domain and relative `feed_path`; redirects, alternate hosts, query-bearing paths, fragments, IP literals and traversal paths are rejected. Provider/platform adapters use fixed documented endpoints and still constrain evidence to the registered canonical source domain. All automated observations enter the same approved-source provenance, verification, quarantine, trust and fact-reconciliation path.

## Installed pull adapters

The registry contains ten adapters:

- `json-feed-v1`
- `rss-atom-v1`
- `structured-html-v1`
- `x-api-v2-kingshot-v1`
- `century-games-kingshot-news-rss-v1`
- `discord-channel-v1`
- `youtube-channel-v1`
- `reddit-data-api-v1`
- `facebook-page-v1`
- `instagram-media-v1`

Installing an adapter does not approve a source and does not enable ingestion.

### JSON feed adapter

`json-feed-v1` retrieves a bounded JSON document with explicit observation fields. The publisher must respect the requested limit and may accept the opaque `cursor` query parameter. Source URLs remain constrained to the registered canonical domain.

### RSS/Atom adapter

`rss-atom-v1` accepts bounded RSS or Atom XML from the configured `feed_path`. It never infers Gift Codes from article prose or nested content. An entry must contain an explicit direct-child machine Gift Code element such as `<ks:gift-code>` or `<ks:code>`. DTD/entity declarations, malformed XML, unsupported cursors and over-limit documents fail closed.

### Structured HTML adapter

`structured-html-v1` accepts explicit machine-readable HTML only. A Gift Code observation is an element with `data-gift-code`; ordinary page text is ignored. Optional `data-gift-code-*` attributes carry assertion, source URL, expiry, publication, payload and version metadata.

### Official X API adapter

`x-api-v2-kingshot-v1` uses the documented X user-post timeline. Configure `GIFT_CODES_X_BEARER_TOKEN` server-side. Source policy requires canonical domain `x.com`, confirmed `x_user_id`, and expected `x_username`. Only whole lines explicitly labelled `Gift Code:` or `Redeem Code:` are accepted.

### Century Games Kingshot-news RSS adapter

`century-games-kingshot-news-rss-v1` is provider-permission gated. It requires canonical domain `centurygames.com`, an agreed relative `feed_path`, `provider_permission_confirmed=true`, and exact `gift_code_category`. A category-matched entry that no longer satisfies the explicit label contract fails closed instead of being guessed from prose.

### Discord channel adapter

`discord-channel-v1` uses an installed bot; it never uses a Discord user token or self-bot session. Configure `GIFT_CODES_DISCORD_BOT_TOKEN` server-side. Policy requires:

- canonical domain `discord.com`;
- approved `discord_guild_id` and `discord_channel_id`;
- one-to-fifty approved `discord_author_ids`;
- `platform_permission_confirmed=true` before enabled ingestion;
- `message_content_access_confirmed=true` before enabled ingestion.

The adapter verifies channel/guild scope, ignores non-allowlisted authors and accepts only explicit Gift Code labels. Evidence URLs use Discord's canonical channel/message URL.

### YouTube channel adapter

`youtube-channel-v1` uses the YouTube Data API with `GIFT_CODES_YOUTUBE_API_KEY`. Policy requires canonical domain `youtube.com`, confirmed `youtube_channel_id` and `youtube_channel_title`, and `platform_api_access_confirmed=true` before ingestion can be enabled.

The adapter resolves the channel's uploads playlist through `channels.list` and reads it with `playlistItems.list`; it does not depend on search indexing or scrape video pages. Only explicit Gift Code labels in supported upload metadata become observations.

### Reddit discovery adapter

`reddit-data-api-v1` requires registered Data API access plus `GIFT_CODES_REDDIT_CLIENT_ID`, `GIFT_CODES_REDDIT_CLIENT_SECRET`, and a descriptive `GIFT_CODES_REDDIT_USER_AGENT`. Policy requires `platform_api_access_confirmed=true` to enable ingestion and a valid subreddit.

Reddit is an independent discovery signal only:

- source classification must be `independent`;
- `auto_verify=true` is rejected;
- API retrieval success does not independently make a Gift Code valid;
- if Reddit API access is unavailable, keep the adapter disabled and use reviewed registered-source manual evidence where appropriate.

### Facebook Page adapter

`facebook-page-v1` uses the documented Meta platform path only after Page access is confirmed. Configure `GIFT_CODES_FACEBOOK_ACCESS_TOKEN` server-side. Policy requires canonical domain `facebook.com`, confirmed numeric `facebook_page_id`, expected `facebook_page_name`, and `platform_permission_confirmed=true` before ingestion can be enabled. Returned Page identity is checked before posts are parsed.

### Instagram media adapter

`instagram-media-v1` is for an authorized professional-account API path, not consumer-account scraping. Configure `GIFT_CODES_INSTAGRAM_ACCESS_TOKEN`. Policy requires canonical domain `instagram.com`, confirmed `instagram_user_id`, expected `instagram_username`, and `platform_permission_confirmed=true` before ingestion can be enabled. Account identity is checked before media captions are parsed.

`GIFT_CODES_META_GRAPH_API_VERSION` controls the configured Meta API version and defaults to the currently reviewed version rather than persisting API version data in source rows.

## Registered-source manual evidence

`RecordRegisteredGiftCodeEvidence` provides the supported path for a reviewed source that has no legitimate machine contract. This is the normal current path for the Official Wiki and researched Stage 3 publishers unless they later publish a documented structured feed.

A registered source must explicitly have:

```json
{
  "manual_evidence_allowed": true,
  "auto_verify": false
}
```

An MFA-authorized Gift Code curator records the exact publication URL. The URL must use HTTPS on the registered canonical domain. The resulting provenance is append-only, verified as a curator-confirmed registered-source observation, and retains the registered source id.

Independent manual evidence does not bypass corroboration. Two pages from one registered publisher remain one independent source identity; the configured independent-source threshold still requires distinct registered sources.

Reward and applicability manual evidence requires a structured assertion payload and continues through the existing fact resolver.

## Source-management surface

`/platform/gift-codes/sources` is the dedicated researched-source policy surface. It shows:

- the research-only staged catalogue;
- the installed adapter list;
- registered source ingestion/manual-evidence state;
- platform/provider policy inputs required by each adapter;
- a curated manual-evidence form for sources explicitly allowed to use it.

The richer source-policy POST is separate from the existing generic source registration POST used by `/platform/gift-codes`, preserving the older generic source workflow.

Secrets must never be entered into source policy. They belong in environment/application configuration.

## Assertion and webhook rules

Supported assertions are `available`, `invalid`, `expires`, `reward`, and `applicability`. Reward and applicability values belong in the assertion payload. Automated verification still requires source policy `auto_verify=true`; otherwise an automated observation is quarantined for review.

Signed source webhook ingestion remains a transport into the same approved-source observation action, not a separate evidence/trust engine. It enforces registered-source status, signature/timestamp/replay checks and payload bounds before ingestion.

The research catalogue is informational only. It never creates a source registry row and never sets classification, `auto_verify`, or `ingestion_enabled`. See [Gift Code researched-source rollout](../product/gift-code-researched-source-rollout.md).

## Scheduled processing

- `gift-codes:ingest-approved-sources --limit=25 --cycle` runs every 15 minutes. Use `--source=<source-key>` for targeted replay.
- `gift-codes:reconcile-source-policies --limit=500` runs every five minutes.
- `gift-codes:maintain --limit=500 --cycle` runs every 15 minutes for expiry and availability/trust campaigns.
- `notifications:deliver --limit=100` performs the Communications delivery/retry path every minute.

Every command emits a bounded JSON receipt. Ingestion health and recent runs expose last-attempt, success, failure, stale, accepted, duplicate and quarantine state.

## External activation checklist

Implementing an adapter does not grant access to an external platform. Before enabling any external source:

1. confirm the real source/account/channel identity;
2. confirm required provider/platform permission;
3. configure the server-side credential;
4. register the source with correct canonical domain and classification;
5. start with `auto_verify=false` unless authority and parser semantics justify otherwise;
6. explicitly enable ingestion;
7. review ingestion health and quarantined observations.

Century Games permission, Discord bot/channel scope, Meta account/Page access, YouTube Data API access and Reddit registered API access are external facts that repository code cannot manufacture.

## Incident and rollback procedure

1. Disable only the affected feature/source and refresh application configuration.
2. Preserve ingestion runs, evidence, moderation decisions, notification deliveries, audit events and outbox records; never edit/delete provenance.
3. Revoke or disable a bad source and run `gift-codes:reconcile-source-policies --limit=500` until complete.
4. Correct policy/parser configuration, re-enable the source and run targeted ingestion replay.
5. Confirm `production:check`, source health, failed jobs, outbox backlog and delivery diagnostics before closing the incident.

Source revocation and policy reconciliation are the trust rollback. The Century Games redemption center remains a user handoff; no operator command automates provider redemption.

## Explicit operational exclusions

Do not operate or add:

- Gift Code Center reverse engineering or automated redemption;
- generic prose scraping of Wiki/editorial/social sites;
- undocumented provider APIs;
- Discord self-bots or user-token automation;
- browser/session replay to collect protected social content;
- a shared Stage 3 publisher identity that can falsely satisfy independent corroboration.
