# Cross-context workflows

Status: Current — Architecture V3

`app/Workflows` exists only for commands whose business process genuinely coordinates more than one bounded-context owner.

## V3 workflow packages

```text
app/Workflows/
├── AccountOnboarding/
└── KingdomGovernance/
```

### AccountOnboarding

Coordinates account registration/onboarding behavior that crosses Accounts and Alliance membership/invitation boundaries. The Workflow calls owner Actions such as Accounts registration and Alliance invitation acceptance; it does not query or mutate foreign models directly.

### KingdomGovernance

Coordinates a Kingdom governance process when execution requires GameWorld governance plus another owning context. GameWorld remains owner of Kingdom governance state and Operations remains owner of Operations state.

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

The preferred model is for each owning context to protect its own write transaction and invariants. A Workflow coordinates owner operations rather than becoming the place where foreign persistence and authorization are implemented.