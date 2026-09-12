# Consistency and transactions

Status: Current — Architecture V3

Consistency boundaries follow the capability that owns the write.

## Owner transaction

An owning capability Action is responsible for the transaction required to preserve its invariants:

```text
Action
  -> begin transaction
  -> lock mutable scope/authority state
  -> assert current owner authorization/invariants
  -> lock target aggregate in deterministic order
  -> mutate owner state
  -> persist audit/outbox intent when required
  -> commit
```

## Authorization separation

Authorization services interpret the owner's permission vocabulary. They do not own transactions or acquire database locks.

The owner write path acquires the state/aggregate locks needed to make the authorization decision and write atomic.

`*MutationAuthority` classes that combine these responsibilities are not part of V3.

## HTTP boundary

Controllers, middleware and route closures do not own business write transactions, direct persistence or business locks.

## Cross-context consistency

A context does not extend its transaction boundary by reaching through another context's Eloquent model. Cross-context mutation uses explicit owner Actions.

When a process genuinely spans multiple owners, a Workflow coordinates those owner operations. The Workflow does not become persistence owner of participating aggregates.

The two AccountOnboarding commands `RegisterAccount` and `AcceptInvitationForAccount` compose dependent owner Actions inside one bounded database transaction under [ADR-0019](adr/0019-atomic-account-onboarding-owner-composition.md). Failure rolls back registration, Player claiming and invitation acceptance together. Accounts supplies the current locked account snapshot; participating owners keep their business locks, validation and all writes. Verification mail waits for the outermost commit. The architecture verifier permits transactions only for these explicitly reviewed Workflow commands and continues to forbid direct persistence and model access.

ExternalEventParticipation additionally composes current Operations scope, Integrations admission and the normal Participation action atomically under [ADR-0060](adr/0060-atomic-external-participation-scope-order.md). Owner APIs retain every lock and write; lower integration lock contention rolls back the complete operation and returns a retryable conflict.

Where atomic multi-owner database mutation would create ownership leakage, prefer explicit process state and durable events/outbox coordination.

## Side effects

Remote/retryable effects execute after commit. Durable intent that must survive process failure is stored transactionally with the owner state when required.

Consumers must tolerate at-least-once delivery through idempotency/deduplication.
