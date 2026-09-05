# Gift Code researched-source rollout

Status: **Stage 1 adapter implementation complete; source approval remains an operator decision**

Research reviewed: 2026-09-05.

This rollout extends the existing approved-source framework. It does not create a second trust engine, does not pre-approve a publisher, and does not make a source authoritative merely because it appears in the research catalogue.

The code catalogue is `GiftCodeResearchedSourceCatalogue`. Every entry is `research_only`; the catalogue intentionally contains no `approved`, `auto_verify`, or `ingestion_enabled` fields. A Platform Administrator must still create or revise the corresponding `GiftCodeSourceRegistry` record, choose `official` or `independent`, set policy, and explicitly enable ingestion. Canonical trust remains `IngestApprovedGiftCodeObservation` -> append-only provenance -> `GiftCodeTrustResolver`/fact reconciliation.

## Rollout stages

| Stage | Sources | Intended transport | Current implementation boundary |
| --- | --- | --- | --- |
| 0 | Century Games cooperative source | signed webhook, cooperative JSON, cooperative RSS | Existing signed webhook and generic JSON/RSS adapters are usable only after an explicit provider contract and source-policy approval. |
| 1 | Official Kingshot X; Century Games Kingshot-news RSS | documented X API v2; permissioned provider RSS | `x-api-v2-kingshot-v1` and `century-games-kingshot-news-rss-v1` are installed. Neither creates a source registry row or enables itself. |
| 2 | Kingshot Official Wiki; official Discord | structured feed; legitimate installed Discord bot | Research catalogue only. No wiki prose scraping, no Discord self-bot/user-token automation. |
| 3 | Kingshot.net, Kingshot Optimizer, Kingshot Mastery, Kingshot Atlas, selected editorial publishers | documented structured feed or manual evidence | Independent corroboration candidates only. Each publisher must remain a distinct source identity so the multi-source threshold cannot be satisfied by one publisher. |
| 4 | Facebook, Instagram, Reddit, YouTube | documented platform APIs or manual evidence | Platform-dependent redundancy/discovery only until each integration is separately reviewed. |

## Stage 1 — official X adapter

`x-api-v2-kingshot-v1` uses the documented X API v2 user-post timeline:

- `GET https://api.x.com/2/users/{id}/tweets`;
- bearer token stored only in `GIFT_CODES_X_BEARER_TOKEN` / application configuration;
- source policy stores the separately confirmed numeric X user id and expected username;
- the request asks for `author_id` and user expansion data and verifies the returned author identity before producing observations;
- evidence URLs use `https://x.com/{username}/status/{post-id}` so canonical provenance remains on the registered `x.com` source domain;
- only a whole line explicitly labelled `Gift Code:` or `Redeem Code:` is accepted. The adapter does not search for arbitrary uppercase tokens, URLs, hashtags, captions, or other prose heuristics;
- posts without that explicit grammar produce no observation;
- adapter verification success still does not imply verified evidence unless the registered source policy separately enables `auto_verify`.

Recommended source policy shape after the account identity has been confirmed:

```json
{
  "auto_verify": false,
  "x_user_id": "<confirmed numeric X user id>",
  "x_username": "<confirmed X username>"
}
```

Start with `auto_verify: false`. Promote to automatic verification only after the account authority, parser behavior, and operational failure path have been reviewed.

## Stage 1 — Century Games Kingshot-news RSS adapter

`century-games-kingshot-news-rss-v1` is deliberately permission-gated:

- canonical source domain must be `centurygames.com`;
- `provider_permission_confirmed` must be `true` before ingestion can be enabled;
- the agreed feed path is relative and is fetched only from `https://www.centurygames.com` with redirects disabled;
- the policy must identify the agreed `gift_code_category`;
- only an RSS/Atom entry carrying that exact category is eligible;
- an eligible entry must then match the explicit `Gift Code:` / `Redeem Code:` label contract in its title or description/summary;
- unrelated Century Games news entries are ignored;
- a category-matched entry that no longer satisfies the explicit code contract is a parser failure, not a guessed observation;
- DTD/entity declarations, off-domain evidence links, malformed XML, unbounded documents and cursor use fail closed.

Example policy after provider permission and feed semantics are confirmed:

```json
{
  "auto_verify": false,
  "feed_path": "/agreed/kingshot-feed.xml",
  "provider_permission_confirmed": true,
  "gift_code_category": "kingshot-gift-code"
}
```

The adapter is not authorization to consume a Century Games feed. The permission flag records the operator's external authorization decision; the code cannot manufacture that permission.

## Authority and corroboration rules

- Catalogue presence is not source approval.
- An installed adapter is not source approval.
- `verificationPassed=true` from an adapter is transport/parser evidence only; `auto_verify` remains source policy.
- Official and independent classifications remain distinct.
- Independent publications still require the existing configured multiple-source threshold before they can qualify canonical availability/invalidity/expiry facts.
- Selected editorial publishers must be registered independently; never aggregate several publishers behind one registered source id.
- A source revocation or policy change uses the existing reconciliation job and does not rewrite provenance.

## Explicit exclusions

Do not implement or approve:

- Gift Code Center endpoint reverse engineering or automated redemption;
- generic prose scraping of Century Games, the Official Wiki, editorial sites, or social pages;
- undocumented provider APIs;
- Discord self-bots or user-token automation;
- authentication/session replay against Facebook, Instagram, Reddit, YouTube, X, Discord, or Century Games;
- a shared Stage 3 "editorial" source identity that would defeat independent-source counting;
- automatic registry creation, automatic `official` classification, automatic `auto_verify`, or automatic `ingestion_enabled` based on the research catalogue.

## Research anchors

The implementation was checked against the public first-party surfaces and API documentation available on 2026-09-05:

- Century Games Kingshot: `https://www.centurygames.com/games/kingshot/`
- Century Games news: `https://www.centurygames.com/news/`
- Kingshot Official Wiki: `https://kingshotwiki.com/`
- X API Get Posts: `https://docs.x.com/x-api/users/get-posts`
- X API authentication mapping: `https://docs.x.com/fundamentals/authentication/guides/v2-authentication-mapping`
- Kingshot.net: `https://kingshot.net/`
- Kingshot Optimizer: `https://kingshotoptimizer.com/`
- Kingshot Mastery: `https://kingshotmastery.com/`
- Kingshot Atlas: `https://ks-atlas.com/`

These references establish candidate existence or API shape only. They are not repository source-policy approvals.
