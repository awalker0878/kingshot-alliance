# Gift Code operations

Status: Current

## Launch configuration

Staging and production must explicitly enable `GIFT_CODES_MODERATION`, `GIFT_CODES_APPROVED_SOURCE_INGESTION`, and `GIFT_CODES_NOTIFICATION_FANOUT`. `production:check` reports a failed Gift Code launch check while any flag is disabled. Disabling a flag remains the immediate rollback control for that slice.

Before approved-source ingestion is launch-ready, a Platform Administrator with verified email, MFA, and recent password confirmation must register at least one active source in `/platform/gift-codes`. The source must use an adapter listed by that screen. `production:check` fails when ingestion is enabled without an active source or when an enabled source references an unavailable adapter.

All built-in pull adapters retrieve HTTPS content from the registered canonical domain. Source policy stores only a relative `feed_path`; redirects, alternate hosts, query-bearing paths, fragments, IP literals, and traversal paths are rejected. Pull adapters are bounded by the configured document and observation limits and feed observations into the same approved-source provenance, verification, quarantine, trust and fact-reconciliation path.

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

### Assertion and webhook rules

Supported assertions across approved-source ingestion are `available`, `invalid`, `expires`, `reward`, and `applicability`. Reward and applicability values belong in the assertion payload. Automatic verification still requires the registered source policy to enable `auto_verify`; otherwise observations enter the quarantine review queue.

Signed source webhook ingestion is a transport into the same approved-source observation action, not a pull adapter or a separate evidence/trust engine. It enforces registered-source status, signature/timestamp/replay checks and payload bounds before canonical ingestion.

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
