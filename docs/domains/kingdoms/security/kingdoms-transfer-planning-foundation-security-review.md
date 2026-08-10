# KINGDOMS-002 transfer-cycle foundation security review

**Scope:** `K2-P0` decisions + Slice A / `K2-P1`  
**Status:** Candidate review evidence

## Protected assets

- Alliance-owned transfer-cycle existence, labels, dates, lifecycle state, and captured home-Kingdom context.
- Privileged lifecycle authority.
- Audit and durable event history.

## Primary threats and controls

### Cross-alliance object-ID tampering

Every lifecycle action re-resolves the submitted plan ID with the active `alliance_id` while the Alliance row is locked. A valid plan ID from another tenant therefore resolves as not found rather than becoming an authorization path.

### Permission escalation

Ordinary member reads require `alliance.view`. Management reads and all lifecycle mutations require `kingdoms.manage`; mutations additionally pass the existing recent-password-confirmation middleware. Controller role-name checks are not introduced.

### Concurrent conflicting open cycles

Opening a plan locks the Alliance row so same-Alliance opens serialize. A PostgreSQL partial unique index on `alliance_id WHERE state = 'open'` supplies a database-side invariant if application concurrency assumptions fail.

### Home-Kingdom drift

A plan captures the Alliance Kingdom at creation. Open/lock/close compare current Alliance Kingdom with the captured reference and fail closed when they differ. Cancellation is intentionally permitted after drift as a terminal recovery action.

### Lifecycle replay

Repeating a transition whose target state is already persisted returns the plan without writing another audit event or outbox message. Invalid state jumps fail validation.

### Accidental external exposure

All new durability events use the `kingdoms.*` namespace. The accepted Integrations boundary rejects that namespace from generic outbound webhook fan-out, including wildcard subscriptions.

### Premature capability/schema expansion

The migration contains only transfer-cycle fields. Architecture coverage guards against participant/group/readiness/coordinator/destination fields entering Slice A as dormant schema.

## Privacy result

The member view contains only approved cycle metadata. There are no participant identities or manager-only transfer notes in Slice A, so no broader roster/player disclosure is introduced.

## Residual risks deferred by scope

Player identity ambiguity, incoming-player privacy, destination validation, group/coordinator privacy, readiness/blocker sensitivity, and roster handoff safety are reviewed in the slices that actually implement those capabilities. They are not mitigated through unused Slice A schema.
