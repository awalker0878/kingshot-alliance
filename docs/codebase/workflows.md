# Cross-context workflows

Status: Current — Architecture V3

`app/Workflows` exists only for commands whose business process genuinely coordinates more than one bounded-context owner.

## V3 workflow packages

```text
app/Workflows/
├── AccountOnboarding/
├── ExternalEventParticipation/
├── KingdomGovernance/
└── NotificationDelivery/
```

### AccountOnboarding

Coordinates account registration/onboarding behavior that crosses Accounts and Alliance membership/invitation boundaries. The Workflow calls owner Actions such as Accounts registration and Alliance invitation acceptance; it does not query or mutate foreign models directly.

### KingdomGovernance

Coordinates a Kingdom governance process when execution requires GameWorld governance plus another owning context. GameWorld remains owner of Kingdom governance state and Operations remains owner of Operations state.

### ExternalEventParticipation

Owns the multi-context bot/API write adapter and coordinates Platform external-actor identity and idempotency with Operations participation Actions. Platform remains owner of provider links and receipts; Operations remains owner of Event response, registration, capacity, and waitlist rules.

### NotificationDelivery

Coordinates the bounded Officer Brief and Intelligence notification sweeps through owner-authorized ReadModels and Communications intent. It also binds the delivery-side source authorization port to current account/Governor and source-owner checks for queued external publication. It does not own provider retries, endpoint state, source Models, permission enums, migrations or transactions. The source check is part of a cross-owner publication command, not another user-facing ReadModel. See [ADR-0018](../architecture/adr/0018-notification-orchestration-workflow.md) and [ADR-0046](../architecture/adr/0046-current-notification-source-authorization.md).

## What is not a Workflow

- active Player selection/activation belongs to `GameWorld/Players`;
- Kingdom transfer behavior belongs to `GameWorld/KingdomTransfers`;
- a single-context command belongs to that context capability;
- a cross-context read belongs to `app/ReadModels`.

## Workflow rules

A Workflow may:

- sequence explicit owner Actions;
- pass stable scalar identifiers and command data;
- coordinate a multi-owner process and failure handling;
- publish/consume process events where appropriate.

A Workflow must not:

- contain business Models;
- own migrations or repositories;
- define another context's permission vocabulary;
- directly call `save`, `delete`, `create`, `update` or equivalent persistence on foreign aggregates;
- acquire domain locks for participating owners;
- become a persistence owner simply because it coordinates a transaction;
- provide a compatibility façade for code that belongs in a context.

## Transaction boundary

Owner Actions protect domain invariants and acquire domain locks. Only the two explicit AccountOnboarding commands reviewed in [ADR-0019](../architecture/adr/0019-atomic-account-onboarding-owner-composition.md) may wrap their dependent owner calls in a bounded same-database transaction. The architecture verifier rejects other Workflow transactions, foreign Model access, raw persistence, business locks and foreign permission vocabularies. NotificationDelivery does not gain a transaction exception by coordinating source authorization.
