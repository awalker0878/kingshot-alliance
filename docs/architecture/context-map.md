# Context map

Status: Current — Architecture V3

The system has exactly seven business bounded contexts:

```text
Accounts ---------> GameWorld <--------- Alliance
   |                   |                    |
   |                   |                    |
   |                   v                    v
   |              Operations ----------> Intelligence
   |                   |                    |
   |                   v                    |
   +-------------> Communications <---------+
                       |
                       v
                    Platform
```

Arrows indicate permitted collaboration, not unrestricted model dependencies.

## Context responsibilities

- **Accounts** owns User account identity and account security capabilities.
- **GameWorld** owns Players, Kingdoms, Kingdom governance, Kingdom transfers, and immutable factual progression releases/topology/calculator rules.
- **Alliance** owns Alliance lifecycle, membership/leadership, Alliance access, recruitment and content.
- **Operations** owns Event execution and operational coordination.
- **Intelligence** owns observational and analytical state, including dated Governor progression observations and their evidence provenance.
- **Communications** owns generic notification delivery behavior.
- **Platform** owns cross-tenant application administration.

## Collaboration rules

### Accounts → GameWorld

An authenticated User may own Players. Accounts does not own Player game state. Cross-context ownership uses scalar `user_id` references and explicit owner contracts rather than a GameWorld Eloquent relationship into Accounts.

### GameWorld → Alliance / Operations / Intelligence

GameWorld exposes neutral Player, Kingdom, placement, governance and factual progression facts. Downstream contexts interpret those facts using their own business language and permission semantics.

`GameWorld/Progression` owns immutable progression releases/checksums, factual state ladders/prerequisites, calculator evidence qualification, and pure dataset-pinned calculation rules. It does not own an individual Governor's observed current state or Alliance authorization.

### Alliance → Operations / Intelligence

Alliance exposes current Player-scoped membership/rank/role facts. Operations and Intelligence decide what those facts authorize inside their own capabilities.

### Operations → Intelligence

Operations owns live Event execution and results. Intelligence consumes operational facts for history, observation and analysis without mutating Operations aggregates.

### Operations → Communications

Operations owns Event/King Perk reminder meaning and timing. Communications receives generic delivery requests and owns delivery state only.

### Platform → business contexts

Platform owns SaaS/platform administration. Platform Administrator is User-scoped platform authority and is never a game-domain authorization bypass.

## Composition

Cross-context commands use `app/Workflows` only when more than one owner participates. V3 workflow packages are `AccountOnboarding`, `ExternalEventParticipation`, and `KingdomGovernance`.

Cross-context read composition uses `app/ReadModels`.

The Progression Goal Planner is a concrete example: `ReadModels/Progression` re-acquires active Player/Alliance Intelligence authority before retrieving an observed current state, then composes that authorized Intelligence projection with immutable `GameWorld/Progression` topology and per-family calculator eligibility. It persists no duplicated factual or observational truth. Calculation operates only on authorized scalar current/target inputs plus the pinned GameWorld dataset.

Business-neutral infrastructure uses `app/Shared/Infrastructure`.

## Dependency test

Before introducing a cross-context dependency, ask:

1. Which context and capability owns the invariant?
2. Can the caller use a scalar identifier and owner Query/Action instead of a foreign Model?
3. Is this one owner's write or a true multi-owner Workflow?
4. Is this only a composed read and therefore a ReadModel?
5. Would a durable event remove direct persistence coupling?

If ownership is unclear, resolve the capability boundary before adding the dependency.
