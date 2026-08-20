# ADR-0008: Bound operator recovery and expose privacy-safe diagnostics

Status: Accepted

Date: 2026-08-20

## Context

Automatic outbox retries previously had no terminal attempt limit, while platform metrics did not identify exhausted work or let an operator correlate one request across its audit records. Showing raw exceptions, payloads or provider responses would create a second sensitive-data surface.

## Decision

1. Outbox publication stops claiming a message after the configured maximum attempt count.
2. The Citadel read model composes bounded recent outbox, webhook, notification and failed-job projections. It exposes operational identifiers, status and a short deterministic error fingerprint, never raw payloads or exception text.
3. Request UUID or W3C trace-ID lookup returns a bounded audit timeline without audit metadata.
4. A password-confirmed Platform Administrator may release only an exhausted failed unpublished outbox message. Release retains the message and idempotency key, resets one bounded attempt budget, and clears the stored error.
5. Every operator release records the previous attempt count and error fingerprint in the audit trail. It does not create another outbox message.

## Consequences

- Poison messages stop consuming worker capacity indefinitely.
- Operators can group repeated failures without reading secrets or private notification bodies.
- Replay remains explicit, rate-limited, idempotency-preserving and auditable.
- Published work cannot be replayed through the outbox control.

## Rejected alternatives

- Unlimited automatic retry was rejected because one poison message can create permanent load.
- Displaying raw errors was rejected because provider responses may contain URLs, identifiers or request data.
- Deleting and recreating the message was rejected because it would discard durable history and idempotency identity.
