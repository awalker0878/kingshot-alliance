# Gift Code operations

Status: Current

## Launch configuration

Staging and production must explicitly enable `GIFT_CODES_MODERATION`, `GIFT_CODES_APPROVED_SOURCE_INGESTION`, and `GIFT_CODES_NOTIFICATION_FANOUT`. `production:check` reports a failed Gift Code launch check while any flag is disabled. Disabling a flag remains the immediate rollback control for that slice.

Before approved-source ingestion is launch-ready, a Platform Administrator with verified email, MFA, and recent password confirmation must register at least one active source in `/platform/gift-codes`. The source must use an adapter listed by that screen. `production:check` fails when ingestion is enabled without an active source or when an enabled source references an unavailable adapter.

The built-in `json-feed-v1` adapter retrieves an HTTPS JSON document from the registered canonical domain. The source policy stores only a relative feed path such as `/gift-codes.json`; redirects, alternate hosts, query-bearing paths, fragments, IP literals, and traversal paths are rejected.

The feed contract is:

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

Supported assertions are `available`, `invalid`, `expires`, `reward`, and `applicability`. Reward and applicability values belong in `payload`. The publisher must respect the requested `limit` and may accept the opaque `cursor` query parameter. Automatic verification still requires the registered source policy to enable `auto_verify`; otherwise observations enter the quarantine review queue.

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
