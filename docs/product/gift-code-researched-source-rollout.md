# Gift Code researched-source rollout

Status: **research implementation complete; source approval and external credentials remain operator decisions**

Research reviewed: 2026-09-05.

This rollout extends the existing approved-source framework. It does not create a second trust engine, pre-approve a publisher, or make a source authoritative merely because it appears in the research catalogue.

`GiftCodeResearchedSourceCatalogue` remains research-only. Every entry has `catalogue_state=research_only`; the catalogue intentionally contains no `approved`, `auto_verify`, or `ingestion_enabled` fields. A Platform Administrator must still create or revise a `GiftCodeSourceRegistry` record, classify it as `official` or `independent`, confirm the required platform/provider permissions, configure server-side credentials where needed, and explicitly enable ingestion.

Canonical trust remains append-only provenance reconciled by the existing `GiftCodeTrustResolver` and fact resolver. Automated observations still enter through `IngestApprovedGiftCodeObservation`; non-automatable reviewed publications enter through `RecordRegisteredGiftCodeEvidence` under a distinct registered source identity.

## Completion definition

The research is considered implemented when every researched source has a legitimate repository path without inventing a scraper or undocumented automation. That is now true:

- documented/provider-authorized machine interfaces have dedicated or existing adapters;
- sources without a documented machine contract have registered-source manual evidence instead of prose scraping;
- independent publishers remain distinct identities and therefore cannot satisfy corroboration by themselves;
- platform-dependent adapters fail closed until credentials and permission/account identity gates are satisfied;
- research catalogue presence never creates or enables source policy.

This does **not** mean every external source is active in a deployment. Activation still depends on real credentials, permissions, account/channel identity confirmation and an explicit Platform Administrator policy decision.

## Rollout stages

| Stage | Sources | Implemented repository path |
| --- | --- | --- |
| 0 | Century Games cooperative source | Existing signed webhook plus generic JSON/RSS structured-source adapters, gated by provider cooperation and explicit source policy. |
| 1 | Official Kingshot X; Century Games Kingshot news | `x-api-v2-kingshot-v1`; `century-games-kingshot-news-rss-v1`. The Century Games path remains permission-gated. |
| 2 | Kingshot Official Wiki; official Discord | Wiki: registered manual evidence now, with generic JSON/RSS/structured-HTML adapters only if a documented machine contract is later offered. Discord: `discord-channel-v1` using an installed bot, approved guild/channel and author allowlist. |
| 3 | Kingshot.net, Kingshot Optimizer, Kingshot Mastery, Kingshot Atlas, selected editorial publishers | Registered manual evidence under a separate source registry identity for each publisher. A documented structured feed can later use the existing generic adapters. No generic prose scraper is implemented. |
| 4 | Facebook, Instagram, Reddit, YouTube | `facebook-page-v1`, `instagram-media-v1`, `reddit-data-api-v1`, and `youtube-channel-v1`, each with platform-specific permission/identity gates. Manual registered evidence remains available where platform API access is unavailable. |

## Installed pull adapters

The Gift Code adapter registry now exposes ten pull adapters:

1. `json-feed-v1`
2. `rss-atom-v1`
3. `structured-html-v1`
4. `x-api-v2-kingshot-v1`
5. `century-games-kingshot-news-rss-v1`
6. `discord-channel-v1`
7. `youtube-channel-v1`
8. `reddit-data-api-v1`
9. `facebook-page-v1`
10. `instagram-media-v1`

An installed adapter is capability only. It is not source approval.

## Provider and platform boundaries

### X

`x-api-v2-kingshot-v1` uses the documented X user-post timeline for a separately confirmed account. It accepts only explicit whole-line `Gift Code:` / `Redeem Code:` labels and keeps the bearer token in `GIFT_CODES_X_BEARER_TOKEN`.

### Century Games

`century-games-kingshot-news-rss-v1` requires an agreed relative feed path, an agreed Gift Code category, `provider_permission_confirmed=true`, and `centurygames.com` evidence URLs. It does not scrape Century Games page prose.

### Discord

`discord-channel-v1` uses Discord's bot authorization model, not a user token. Source policy identifies the approved guild, channel and one-to-fifty approved author ids. Ingestion can be enabled only when bot installation/channel scope and message-content access are explicitly confirmed and `GIFT_CODES_DISCORD_BOT_TOKEN` is configured. Self-bots remain prohibited.

### YouTube

`youtube-channel-v1` uses the YouTube Data API. It verifies the configured channel id/title, resolves that channel's uploads playlist through `channels.list`, and reads uploads through `playlistItems.list`. It does not use web-page scraping or broad search-result inference. `GIFT_CODES_YOUTUBE_API_KEY` remains server-side.

### Reddit

`reddit-data-api-v1` is deliberately discovery-only. It requires a registered Data API application, OAuth credentials and a descriptive User-Agent. Reddit source classification is forced to `independent`, and `auto_verify=true` is rejected. If Data API access is unavailable during Reddit's platform transition, the adapter remains disabled and reviewed publications may be recorded manually instead.

Credentials are `GIFT_CODES_REDDIT_CLIENT_ID`, `GIFT_CODES_REDDIT_CLIENT_SECRET`, and `GIFT_CODES_REDDIT_USER_AGENT`.

### Facebook

`facebook-page-v1` requires confirmed Page identity and platform/Page access. It uses a server-side Page/API access token, validates returned Page identity before reading posts, and accepts only explicit Gift Code labels. The token remains in `GIFT_CODES_FACEBOOK_ACCESS_TOKEN`.

### Instagram

`instagram-media-v1` requires a confirmed professional-account identity and platform permission. It validates the expected account before reading media captions and keeps the token in `GIFT_CODES_INSTAGRAM_ACCESS_TOKEN`. Consumer-account scraping is not implemented.

The Meta Graph API version is configuration (`GIFT_CODES_META_GRAPH_API_VERSION`) rather than hard-coded into source rows.

## Official Wiki and Stage 3 manual evidence

No legitimate documented machine feed was identified for the Official Wiki, Kingshot.net, Kingshot Optimizer, Kingshot Mastery or Kingshot Atlas during the 2026-09-05 review. The correct implementation is therefore not a scraper.

`RecordRegisteredGiftCodeEvidence` allows an MFA-authorized Gift Code curator to record an exact publication URL under an active registered source only when its policy explicitly has:

```json
{
  "manual_evidence_allowed": true,
  "auto_verify": false
}
```

The exact evidence URL must use HTTPS and remain on the registered canonical source domain. The resulting provenance is append-only and retains that registered source id. For independent sources, the normal configured independent-source threshold still applies. Two different publications from the same registered publisher do not count as two independent sources.

Reward and applicability assertions still require structured assertion payloads and continue through the existing evidence-gated fact resolver.

## Source-management surface

Platform source policy now has a dedicated management page at `/platform/gift-codes/sources` while the existing Gift Code moderation page remains compatible with its simpler generic-source workflow.

The source-management surface:

- displays the research catalogue without granting authority;
- can prepare a policy from a catalogue candidate;
- exposes provider/platform policy fields required by the installed adapters;
- keeps secrets out of persisted source policy;
- supports registered-source manual evidence for approved sources;
- displays registered source ingestion/manual-evidence state.

The richer policy POST is separated from the legacy generic source POST so existing moderation behavior is not silently changed.

## Authority and corroboration rules

- Catalogue presence is not source approval.
- An installed adapter is not source approval.
- Adapter `verificationPassed=true` is transport/parser evidence only; registered source policy still controls automatic verification.
- Official and independent classifications remain distinct.
- Independent publications require the configured multi-source threshold before qualifying canonical availability, invalidity or expiry.
- Each Stage 3 publisher must receive its own source registry row; never aggregate publishers behind one id.
- Reddit remains independent discovery even if its API transport succeeds.
- A source revocation or policy revision uses the existing reconciliation path and never rewrites historical provenance.

## Explicit exclusions

Do not implement or approve:

- Gift Code Center endpoint reverse engineering or automated redemption;
- generic prose scraping of Century Games, the Official Wiki, editorial sites or social pages;
- undocumented provider APIs;
- Discord self-bots or user-token automation;
- authentication/session replay against Facebook, Instagram, Reddit, YouTube, X, Discord or Century Games;
- a shared Stage 3 `editorial` source identity that defeats independent-source counting;
- automatic registry creation, automatic `official` classification, automatic `auto_verify`, or automatic `ingestion_enabled` based on research catalogue membership.

## Deployment activation checklist

Research implementation can ship with every external integration disabled. To activate a source, an operator must separately:

1. verify the real source/account/channel identity;
2. confirm any provider or platform permission required by that transport;
3. configure the required server-side credential;
4. create/revise the registered source with the correct canonical domain and classification;
5. begin with `auto_verify=false` unless the source's authority and parser contract justify automatic verification;
6. enable ingestion explicitly;
7. review ingestion health/quarantine results before relying on the source operationally.

## Research anchors

The implementation boundary was checked against public first-party surfaces and platform documentation available on 2026-09-05, including Century Games/Kingshot, the Kingshot Official Wiki, X API, Discord developer documentation, YouTube Data API, Reddit Data API/developer platform information, Meta Graph/Instagram APIs, and the identified independent publishers.

These references establish source existence or a legitimate API shape only. They are never repository source-policy approvals.
