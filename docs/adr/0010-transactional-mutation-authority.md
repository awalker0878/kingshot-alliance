# ADR 0010: Transactional mutation authority boundaries

**Status:** Accepted  
**Date:** 2026-08-14

## Context

Kingshot Alliance has multiple authorization scopes: Alliance, Kingdom, Player, and Platform. Read-time permission checks are necessary for presentation and early request rejection, but they are not sufficient for a mutation. The authority state used by a write can change between an early check and the database update through membership departure, rank/role changes, Kingdom-role changes, Player movement, Alliance lifecycle changes, or Platform grant changes.

Duplicating `lockForUpdate()` and permission checks in individual actions also creates inconsistent lock ordering, unnecessary broad locks, and a growing deadlock/maintenance risk.

## Decision

All domain mutations MUST authorize from current authoritative state inside the same database transaction that performs the write.

Scope-specific mutation-authority services own that boundary:

- **Alliance-scoped mutation** — `AllianceMutationAuthority`; active Player membership is the mutable authority record. Ordinary mutations take a shared Alliance lifecycle lock plus an exclusive actor-membership lock. Alliance-wide invariants such as capacity, singleton state, or leadership use the exclusive Alliance variant.
- **Kingdom-scoped mutation** — a Kingdom mutation-authority boundary locks the current Player/Kingdom authority anchor and re-evaluates exact-Kingdom role authority before mutation.
- **Player-self mutation** — the exact active Player is the principal. The action locks/reloads that Player (and any domain record carrying its authority/state) before changing Player-scoped state; sibling Players owned by the same User are never substituted.
- **Platform mutation** — User authority is valid only for true Platform operations. The action locks/reloads the relevant active Platform Administrator grant before performing a Platform mutation.

Read-only authorization services remain non-locking and may be used by controllers, presenters, queries, and early rejection paths. A successful read-time check never substitutes for mutation-boundary authorization.

## Locking policy

Use the narrowest locks that protect the invariant:

1. authority/lifecycle anchor;
2. parent aggregate when required;
3. target identity/authority row;
4. aggregate being mutated;
5. dependent rows.

When multiple rows of the same type must be locked, acquire them in deterministic key order.

Do not use an exclusive Alliance row lock for ordinary independent writes. Reserve exclusive parent locks for Alliance-wide quotas/capacity, leadership, singleton/open-state transitions, lifecycle transitions, or another explicitly documented cross-row invariant.

Prefer database constraints and conditional/compare-and-set updates where they can express the invariant directly. External side effects occur after durable claim/state transitions; asynchronous consumers remain idempotent and at-least-once safe.

## Domain action contract

A write action must:

1. enter a database transaction;
2. acquire the appropriate scope-specific mutation authority;
3. re-resolve/lock the authoritative aggregate and target state needed by the invariant;
4. validate the transition from the locked state;
5. mutate;
6. write audit/outbox evidence using the authoritative actor/context;
7. commit before external side effects.

Controllers and middleware may perform early authorization for UX, but domain actions remain authoritative and safe when called outside HTTP.

## Consequences

- Authority changes serialize with writes that depend on that authority.
- Alliance suspension/closure cannot race an Alliance-scoped mutation.
- Ordinary writes can remain concurrent instead of sharing an Alliance-wide mutex.
- Lock ordering and authorization behavior become reviewable architectural contracts rather than action-specific conventions.
- Existing actions that directly use read authorization for mutations are migration debt and should be converted to the scope-specific mutation-authority boundary.
- Architecture tests must prevent new mutation Actions from introducing direct read-authorization dependencies where a mutation authority exists.
