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
