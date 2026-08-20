# API reference

Status: Current high-level contract

The versioned API is available under `/api/v1`. Every endpoint uses `throttle:api` and an Alliance-scoped, revocable access key. The shared API limiter permits 120 requests per minute per client IP; adapters should apply their own provider- and command-specific limits as well.

## Authentication

Send the one-time access key as a bearer token:

```http
Authorization: Bearer ks_live_<prefix>.<secret>
Accept: application/json
```

Access keys are created in Alliance Connections. The secret is shown once; only its hash is stored. Each request verifies expiry, revocation, the required scope, and that the owning Alliance is active. Successful use updates the key’s `last_used_at` timestamp.

## Endpoints

| Method/path | Required scope | Purpose |
| --- | --- | --- |
| `GET /api/v1/alliance` | `alliance:read` | Alliance identity and public configuration. |
| `GET /api/v1/events` | `events:read` | Bounded Alliance Event occurrences. |
| `GET /api/v1/contributions` | `contributions:read` | Approved contribution records. |
| `GET /api/v1/commands/overview` | `commands:read` | Bot-ready Alliance identity, ten upcoming Events, active Gift Codes, recent knowledge, and recruitment status. |
| `GET /api/v1/commands/gift-codes?limit=20` | `gift-codes:read` | Up to 50 active, unexpired Gift Codes with source metadata and the official redemption URL. |
| `GET /api/v1/commands/knowledge?q=&type=&limit=20` | `content:read` | Up to 50 published Alliance knowledge excerpts with visibility and provenance. |

Knowledge `type` accepts the Content catalogue values: `announcement`, `guide`, `rule`, `event_instruction`, and `reference_page`.

## Response envelope

Command endpoints return:

```json
{
  "data": {},
  "meta": {
    "generated_at": "2026-08-20T00:00:00Z",
    "read_only": true
  }
}
```

Payloads are bounded and read-only. The command overview does not expose Governor accounts, candidate records, application answers, recruiter notes, private notification endpoints, or credential secrets.

A recruitment application URL returned to a bot carries the visible `bot-command` source. This is ordinary application metadata, not an identity tracking token.

## Adapter boundary

Discord and Telegram adapters should translate chat commands into these HTTP reads. They must not duplicate Event, Gift Code, Content, or Recruitment business logic. Provider signing verification, command registration, response formatting, and chat rate limits stay in the adapter.

API access does not grant Platform Administrator authority or write access. A missing, expired, revoked, malformed, or under-scoped key receives an authentication error.

For public webhook event selectors see [Events](../events.md).
