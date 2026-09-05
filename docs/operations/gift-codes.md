# Gift Code operations

Status: Current

## Launch configuration

Staging and production must explicitly enable `GIFT_CODES_MODERATION`, `GIFT_CODES_APPROVED_SOURCE_INGESTION`, and `GIFT_CODES_NOTIFICATION_FANOUT`. `production:check` reports a failed Gift Code launch check while any flag is disabled. Disabling a flag remains the immediate rollback control for that slice.

Before approved-source ingestion is launch-ready, a Platform Administrator with verified email, MFA, and recent password confirmation must register at least one active source in `/platform/gift-codes`. The source must use an adapter listed by that screen. `production:check` fails when ingestion is enabled without an active source or when an enabled source references an unavailable adapter.

The generic JSON/RSS/HTML pull adapters retrieve HTTPS content from the registered public canonical domain and a relative `feed_path`; redirects, alternate hosts, query-bearing paths, fragments, IP literals, and traversal paths are rejected. Provider-specific adapters use fixed documented provider endpoints and still constrain resulting evidence URLs to the registered canonical source domain. All pull adapters are bounded and feed observations into the same approved-source provenance, verification, quarantine, trust and fact-reconciliation path.

### JSON feed adapter

The `json-feed-v1` adapter retrieves a bounded JSON document. The feed contract is:

```json
{
  "version": "publisher-version",
  "next_cursor": "opaque-cursor-or-null",
  "items": [
    {
      "code": "EXAMPLE-CODE",
      "assertion": "available",
      "payload": null,
      "source_url": "https://publisher.example/gift-codes/example-code",
      "expires_at": "2026-09-30T23:59:00Z",
      "expiry_precision": "minute",
      "expiry_timezone": "UTC",
      "published_at": "2026-08-31T12:00:00Z",
      "version": "item-version"
    }
  ]
}
```

The publisher must respect the requested `limit` and may accept the opaque `cursor` query parameter.

### RSS/Atom adapter

The `rss-atom-v1` adapter accepts RSS or Atom XML from the configured `feed_path`. It does not infer Gift Codes from article prose or nested content blocks. Each RSS `<item>` or Atom `<entry>` must contain an explicit direct-child machine-readable Gift Code element such as `<ks:gift-code>` or `<ks:code>`. Optional direct-child metadata includes `assertion`, expiry, expiry precision/timezone and publication time; RSS/Atom links may provide the evidence URL and remain subject to the canonical-domain check.

When no assertion is supplied, the adapter emits `available`. The adapter rejects DTD/entity declarations, malformed XML, cursors and documents or entry sets that exceed configured bounds.

Example:

```xml
<rss version="2.0" xmlns:ks="https://kingshot.app/gift-codes">
  <channel>
    <item>
      <link>https://publisher.example/gift-codes/example-code</link>
      <ks:gift-code>EXAMPLE-CODE</ks:gift-code>
      <ks:assertion>available</ks:assertion>
      <ks:expires-at>2026-09-30T23:59:00Z</ks:expires-at>
      <ks:expiry-precision>minute</ks:expiry-precision>
    </item>
  </channel>
</rss>
```

### Structured HTML adapter

The `structured-html-v1` adapter accepts explicit machine-readable HTML markup only. A Gift Code observation is an element with `data-gift-code`; ordinary page text is ignored. Optional attributes include `data-gift-code-assertion`, `data-gift-code-payload` (JSON object/array), `data-gift-code-source-url`, expiry fields, publication time and source version.

When `data-gift-code-assertion` is omitted, the adapter emits `available`.

Example:

```html
<article
  data-gift-code="EXAMPLE-CODE"
  data-gift-code-assertion="reward"
  data-gift-code-payload='{"rewards":[{"kind":"gold","amount":500}]}'
  data-gift-code-source-url="https://publisher.example/gift-codes/example-code"
>
  EXAMPLE-CODE
</article>
```

### Official X API adapter

The `x-api-v2-kingshot-v1` adapter uses the documented X API v2 user-post timeline at `https://api.x.com/2/users/{id}/tweets`. Configure the bearer token in `GIFT_CODES_X_BEARER_TOKEN`; never place it in source policy or the database.

The source registry must use canonical domain `x.com`. Source policy must contain the separately confirmed numeric `x_user_id` and expected `x_username`. The adapter requests author expansion data and verifies that the returned post author matches both values before producing observations.

The parser accepts only a whole post line explicitly labelled `Gift Code:` or `Redeem Code:` followed by a supported code token. It does not guess from arbitrary uppercase text, hashtags, URLs, captions or other prose. Posts without the explicit grammar produce no observation. Evidence links point to the corresponding `https://x.com/{username}/status/{id}` post.

Example policy:

```json
{
  "auto_verify": false,
  "x_user_id": "<confirmed numeric X user id>",
  "x_username": "<confirmed X username>"
}
```

Keep `auto_verify` false until the Platform Administrator has separately approved the account authority and parser behavior. Installing the adapter or cataloguing the source does not grant authority.

### Century Games Kingshot-news RSS adapter

The `century-games-kingshot-news-rss-v1` adapter is provider-permission gated. It requires canonical domain `centurygames.com`, a relative `feed_path`, `provider_permission_confirmed: true`, and the exact agreed `gift_code_category`. It fetches only from `https://www.centurygames.com` with redirects disabled.

Only entries with the exact configured category are considered. A matching entry must satisfy the explicit `Gift Code:` / `Redeem Code:` label contract in its title or description/summary. Unrelated Century Games news is ignored; a category-matched entry that no longer satisfies the agreed parser contract is quarantined as an unsupported source format rather than guessed from prose. Evidence links must remain HTTPS URLs on `centurygames.com` or its subdomains.

Example policy after external permission and feed semantics have been confirmed:

```json
{
  "auto_verify": false,
  "feed_path": "/agreed/kingshot-feed.xml",
  "provider_permission_confirmed": true,
  "gift_code_category": "kingshot-gift-code"
}
```

The permission flag records an external authorization decision. It is not created by source research and does not represent implied permission to consume a provider feed.

### Assertion and webhook rules

Supported assertions across approved-source ingestion are `available`, `invalid`, `expires`, `reward`, and `applicability`. Reward and applicability values belong in the assertion payload. Automatic verification still requires the registered source policy to enable `auto_verify`; otherwise observations enter the quarantine review queue.

Signed source webhook ingestion is a transport into the same approved-source observation action, not a pull adapter or a separate evidence/trust engine. It enforces registered-source status, signature/timestamp/replay checks and payload bounds before canonical ingestion.

The research catalogue is informational only. It never creates a source registry row and never sets `official`, `auto_verify`, or `ingestion_enabled`. See [Gift Code researched-source rollout](../product/gift-code-researched-source-rollout.md) for staged source candidates and exclusions.

## Scheduled processing

- `gift-codes:ingest-approved-sources --limit=25 --cycle` runs every 15 minutes. Use `--source=<source-key>` for a targeted replay.
- `gift-codes:reconcile-source-policies --limit=500` runs every five minutes.
- `gift-codes:maintain --limit=500 --cycle` runs every 15 minutes. It expires codes, advances expiry-notification cursors, and processes up to `GIFT_CODES_TRANSITION_CAMPAIGNS_PER_RUN` availability/trust campaigns.
- `notifications:deliver --limit=100` performs the existing external-channel delivery and retry path every minute.

Every command emits a bounded JSON receipt. Ingestion health and recent runs are visible on `/platform/gift-codes` with last-attempt, success, failure, stale, accepted, duplicate, and quarantine states.

## Incident and rollback procedure

1. Disable only the affected flag and refresh application configuration.
2. Preserve ingestion runs, evidence, moderation decisions, notification deliveries, audit events, and outbox records; do not edit or delete provenance.
3. For a source incident, revoke or disable the source and run `gift-codes:reconcile-source-policies --limit=500` until its receipt reports completion.
4. Correct source policy or parser behavior, re-enable the flag/source, and run a targeted ingestion replay.
5. Confirm `production:check`, ingestion health, failed jobs, outbox backlog, and delivery diagnostics before closing the incident.

Source revocation and policy reconciliation are the trust rollback. Notification idempotency makes command replay safe. The Century Games redemption center remains a user handoff; no operator command automates provider redemption.
