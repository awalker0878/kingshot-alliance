# ADR 0010 — Transactional mutation and concurrency principles

- **Status:** Accepted
- **Date:** 2026-08-14
- **Owners:** Architecture / all business domains
- **Related scope:** Repository-wide transactional writes, authorization revalidation, concurrency, locking, asynchronous claims
- **Supersedes:** None

## Context

Kingshot Alliance is a domain-first modular monolith. Each domain owns its business mutations, state transitions, persistence rules, and workflow semantics. The repository nevertheless needs one consistent concurrency model so equivalent races are not solved differently in Content, Memberships, Recruitment, Events, Rallies, Contributions, Integrations, Kingdoms, Identity, Platform, or future domains.

Read-time authorization and previously loaded models are not sufficient authority for a write. Authority or state can change before persistence through membership departure, rank/role changes, Kingdom-role changes, Player movement, Alliance lifecycle changes, Platform grant changes, concurrent workflow transitions, or another worker claiming the same work.

Duplicating arbitrary `lockForUpdate()` calls also creates inconsistent lock ordering, unnecessary broad locks, deadlock risk, and hidden scalability problems.

## Decision

Every domain owns its mutation code. There is no repository-wide mutation base class, mandatory generic coordinator, synthetic lock table, or requirement that unrelated domains share one mutation implementation.

All domains MUST, however, follow the same transactional mutation principles.

### 1. Writes re-establish authority and state at the mutation boundary

A mutation must validate the authority and state it depends on from current database state inside the same transaction that performs the write.

Controllers, middleware, policies, query services, and presenters may perform non-locking authorization for UX and early rejection. A successful read-time check never substitutes for mutation-boundary validation.

Where an authority model is shared across domains, a canonical scope-specific authority service may be reused. Business workflow orchestration, aggregate locking, transition validation, and persistence remain owned by the domain performing the mutation.

### 2. Prefer durable database invariants over locks

Use, in order of preference where practical:

1. foreign keys, check constraints, exclusion/unique constraints, and immutable-column guards;
2. atomic conditional or compare-and-set updates;
3. row locking for multi-step invariants that cannot be expressed directly by the database.

A lock is not a substitute for an invariant that PostgreSQL can enforce directly.

### 3. Lock the smallest natural state that protects the invariant

Use the natural aggregate, authority record, claim row, or state row that actually serializes the competing transition.

Examples:

- Alliance authority changes serialize on the active `AllianceMembership` authority record.
- Kingdom authority and Player Kingdom movement serialize through the relevant Player/Kingdom-role state chosen by the Kingdom workflow.
- Player-self mutations lock the exact Player principal when Player state is the invariant anchor.
- Platform operations lock current Platform grant/target state appropriate to that workflow.
- Queue/outbox/webhook workers claim the specific work row atomically rather than locking an unrelated parent aggregate.

Do not introduce a global mutex merely because two records share a parent.

### 4. Parent locks are reserved for parent-wide invariants

An exclusive parent/aggregate lock is appropriate when the invariant is genuinely parent-wide, for example:

- capacity or quota;
- singleton/open-state guarantees;
- leadership or ownership transfer;
- lifecycle transition;
- a parent-wide sequence/counter;
- another documented invariant that spans children.

Ordinary independent child writes should not take an exclusive parent lock.

When a lifecycle boundary must block ordinary writes without serializing them against each other, a shared lifecycle lock plus narrower exclusive child/authority locks is preferred where supported by the persistence implementation.

### 5. Lock ordering is deterministic

Within a workflow, acquire locks in a documented and stable order. The normal conceptual order is:

1. lifecycle/authority anchor needed to establish permission to mutate;
2. parent aggregate only when required by the invariant;
3. target identity or target authority rows;
4. aggregate/work item being mutated;
5. dependent rows.

If multiple rows of the same type are locked, acquire them in deterministic key order.

Domains may refine this order for their own aggregate model, but equivalent operations in that domain must use the same order.

### 6. External side effects are outside database lock scope

Do not perform remote HTTP requests, email delivery, object-storage calls, or other unbounded external I/O while holding database locks.

Durably claim or transition work first, commit, then perform the external side effect. Completion/failure recording must be idempotent. Asynchronous delivery is treated as at-least-once unless the external protocol provides stronger semantics.

### 7. Global/cross-row coordination remains exceptional and domain-owned

If a rare invariant has no natural aggregate or state row, the owning domain may use an explicit transaction-scoped database coordination mechanism. That choice must be documented with the invariant it protects and must not become a generic application-wide locking framework.

Do not create synthetic persistent lock rows or use Redis/cache locks merely to protect PostgreSQL correctness when a natural database invariant or transaction-scoped database mechanism is available.

## Domain action contract

A state-changing action normally follows this sequence:

1. enter a database transaction;
2. acquire/re-establish current mutation authority for the relevant scope;
3. lock/reload only the aggregate and target state required by the invariant;
4. validate the transition from that locked/current state;
5. perform the mutation;
6. record audit/outbox evidence using the authoritative actor and resulting state;
7. commit;
8. perform external side effects only after durable state/claim is established.

The exact classes and services implementing these steps belong to the owning domain.

## Repository governance

New code must follow these principles even when no shared helper exists for its domain.

Architecture tests and reviews should detect regressions such as:

- write actions relying only on pre-transaction authorization;
- broad parent locks for unrelated child mutations;
- inconsistent lock order for the same aggregate family;
- external I/O while database locks are held;
- home-grown distributed locks protecting PostgreSQL invariants;
- writes that can be expressed safely as constraints/CAS but instead use application-only checks;
- audit/outbox records using a stale actor/state snapshot instead of the authoritative mutation context.

Existing workflows that violate these principles are migration debt and should be improved domain by domain without moving their business logic into a central mutation framework.

## Consequences

- Domain ownership remains explicit and the modular-monolith boundaries stay intact.
- Concurrency behavior becomes consistent across the repository without forcing unrelated domains into one implementation.
- Locks are narrower and easier to reason about, improving throughput and reducing deadlock risk.
- Authority changes and state transitions cannot race writes that depend on stale snapshots.
- PostgreSQL remains the source of truth for transactional correctness.
- Future domains inherit architectural rules, not accidental shared business code.
