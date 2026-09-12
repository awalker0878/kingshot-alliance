# Platform — Administration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/Administration`

Administration owns Platform Administrator grants/access and platform-wide administrative behavior that is not game-domain authority. It also owns the operator-facing policy for inspecting privacy-safe runtime diagnostics and releasing an exhausted outbox message for a fresh bounded retry cycle.

## Operational recovery boundary

- Generic outbox claiming and publication remain Shared Infrastructure concerns.
- Administration decides which authenticated Platform Administrators may release an exhausted unpublished message.
- A release preserves the original message and idempotency key, clears the stored error, resets the bounded attempt counter and records an audit event.
- Operator diagnostics expose identifiers, counts, timestamps, correlation values and error fingerprints. They do not expose payloads, secrets or raw exception messages.
- Published messages cannot be released through this workflow.

See [ADR 0008: Bound operator recovery](../../adr/0008-bounded-operator-recovery.md).

## Authority boundary

Platform Administrator is User-scoped. It does not grant Alliance membership, Kingdom governance authority or Operations/Intelligence permissions.

Grant and revocation share the existing transaction coordinator before catalogue rows, including first grants and bootstrap. Grants require the Accounts owner to lock and validate the current active target; a contended account returns a retryable validation error and a finalized account is rejected. This serializes with the deletion blocker without copying account lifecycle rules. See [ADR-0061](../../adr/0061-current-platform-administrator-grants.md).

## Management projections

PlatformAdministration requires current administrator admission for direct and HTTP catalogue reads. Nine independent catalogues use 25-row scoped continuations and complete database totals. Selected Alliance facts remain available off the current page; supporting settings and integration counts are page-scoped. Privacy-safe diagnostic fingerprints are computed before raw errors can cross the database boundary. Older exhausted outbox records remain reachable through the existing recovery Action. See [ADR-0062](../../adr/0062-bounded-platform-administration-catalogues.md).
