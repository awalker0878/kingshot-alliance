# API reference

Status: Current high-level contract

Machine-readable contracts: [OpenAPI 3.1](openapi.json) and [webhook envelope JSON Schema](webhook-envelope.schema.json). Behavior tests compare their paths, required scopes, event catalogue and event-specific required fields with the runtime contracts.

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
| `GET /api/v1/gift-codes?limit=25&cursor=` | `gift-codes:read` | Up to 100 verified active, unexpired Gift Codes with trust/expiry revisions, qualified-or-unknown facts, official handoff URL and opaque cursor metadata. |
| `GET /api/v1/commands/knowledge?q=&type=&limit=20` | `content:read` | Up to 50 published Alliance knowledge excerpts with visibility and provenance. |
| `POST /api/v1/actor-links/claims` | `actor-links:write` | Claim a ten-minute, one-time Discord/Telegram pairing code. |
| `PUT /api/v1/me/events/{occurrence}/response` | `event-participation:write` | Record the linked Governor's Event response and preferences. |
| `PUT /api/v1/me/events/{occurrence}/registration` | `event-participation:write` | Register or cancel the linked Governor through existing capacity/waitlist rules. |

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

Read payloads are bounded. The command overview does not expose Governor accounts, candidate records, application answers, recruiter notes, private notification endpoints, or credential secrets.

The canonical Gift Code endpoint defaults to `status=active` and fails closed if an Alliance credential asks for pending, disputed, expired or history views. Those global review states remain available only through authorized application workflows. `meta.next_cursor` and `meta.previous_cursor` are opaque and must be returned unchanged.

A recruitment application URL returned to a bot carries the visible `bot-command` source. This is ordinary application metadata, not an identity tracking token.

## Adapter boundary

Discord and Telegram adapters translate chat commands into these HTTP contracts. They must not duplicate Event, Gift Code, Content, Recruitment, registration, capacity, or waitlist rules. Provider signing verification, command registration, response formatting, and chat rate limits stay in the adapter.

## External actor pairing and writes

An authenticated Governor creates a provider-specific pairing code under **Account & security → Bot connections**. The code expires after ten minutes, works once, and is stored only as a keyed hash. A credential with `actor-links:write` claims it for a stable numeric Discord/Telegram user ID. The raw provider ID is also stored only as a keyed hash plus a four-digit display hint.

Event writes require `event-participation:write`, an active provider link, and an `Idempotency-Key` header of 8–128 allowlisted characters. Replaying the same normalized request returns the stored result with `meta.replayed: true`; reusing the key for different input fails. Revocation takes effect before any later write. Clients never submit a Player ID as authority.

API access does not grant Platform Administrator or Alliance-manager authority. Write scopes expose only the documented linked-Governor self-service actions. A missing, expired, revoked, malformed, or under-scoped key receives an authentication error.

For public webhook event selectors, scope rules, signing and recovery behavior see [Events](../events.md).
