# System overview

Status: Current — Architecture V3

Kingshot Alliance is a modular Laravel application organized around seven write-owning bounded contexts. Each context is physically divided into cohesive business capabilities rather than context-root framework layers.

```text
Browser / API / Console
        |
        v
Thin HTTP / command adapters
        |
        v
Context capability Actions
        |
        +-------------------------------+
        | Accounts                      |
        | GameWorld                     |
        | Alliance                      |
        | Operations                    |
        | Intelligence                  |
        | Communications                |
        | Platform                      |
        +-------------------------------+
             |          |          +--> Shared/Infrastructure
             |          +-------------> Workflows for true multi-owner commands
             +------------------------> ReadModels for composed reads
```

## Bounded contexts

| Context | Primary responsibility |
| --- | --- |
| Accounts | User identity, registration, authentication, credentials, verification, profile and MFA. |
| GameWorld | Player/Kingdom identity, placement/reference facts, immutable/versioned Kingdom-map truth, Kingdom governance and Kingdom transfers. |
| Alliance | Alliance lifecycle, membership/leadership, Alliance access, recruitment and content. |
| Operations | Event execution, participation, polls, rosters, battle plans, rallies, King Perks, territory/hive planning and results. |
| Intelligence | Observed/ingested facts, roster/contribution intelligence, diplomacy and sharing. Cross-context Event analysis is a ReadModel composition concern. |
| Communications | Generic notification delivery, preferences, channels, retries and idempotency. |
| Platform | Platform administration, Alliance platform administration, data governance, Event administration and integrations. |

## Territory-planning split

The Territory & Hive Planner does not introduce another top-level context.

```text
GameWorld/KingdomMaps
  immutable/versioned map facts
  structures/zones/geometry
  sourced placement rules
  provenance/checksum
          |
          | explicit dataset/query contracts
          v
Operations/TerritoryPlanning
  mutable Alliance/Kingdom plans
  HQ/Banner/city/Bear Trap intent
  planning preferences
  deterministic analysis
  immutable published revisions
          |
          +--> ReadModels/TerritoryPlanning for composed editor reads
          +--> Operations workflows reference published revision IDs
```

`BattlePlans` remains responsible for Event objectives/assignments and does not store spatial state in objective metadata.

## Capability-first source organization

Inside a context, business capability comes before technical implementation layer:

```text
Context/
└── Capability/
    ├── Actions/
    ├── Models/
    ├── Queries/
    ├── Services/
    ├── Policies/
    ├── Http/
    └── Events/
```

Only the folders required by the capability are created.

Context-root technical buckets such as `Accounts/Actions`, `Accounts/Models` or `Platform/Services` are not part of V3.

## Command model

The normal write path is:

```text
HTTP / job / command adapter
        ↓
Owning capability Action
        ↓
transaction + locks + current authorization
        ↓
owner persistence
        ↓
outbox/event intent when required
```

HTTP adapters do not own transactions, direct persistence or domain locking.

TerritoryPlanning follows the same rule. Pointer interaction remains browser working state; an explicit save submits one coherent proposed layout, and the owner Action revalidates active-Player authority, expected revision, map dataset and geometry inside the transaction.

## Cross-context commands

A caller does not manipulate another context's Eloquent models. It calls an explicit owner Action/Query and passes stable scalar identifiers such as `user_id`, `player_id`, `alliance_id`, `kingdom_id`, `kingdom_map_dataset_id` and `territory_plan_revision_id`.

A command requiring more than one write owner is coordinated through a Workflow. V3 keeps Workflows intentionally small:

- `AccountOnboarding`
- `ExternalEventParticipation`
- `KingdomGovernance`

Player activation belongs to `GameWorld/Players`; Kingdom transfer belongs to `GameWorld/KingdomTransfers`.

## Read composition

`app/ReadModels` may combine data from several contexts for a projection. ReadModels are read-only and do not become persistence owners. Territory Command uses this mechanism when it needs to compose immutable map facts, Alliance/Player labels and TerritoryPlanning state.

## Shared infrastructure

`app/Shared/Infrastructure` contains generic infrastructure such as audit, outbox/messaging and runtime mechanics. It owns no game or platform business policy.
