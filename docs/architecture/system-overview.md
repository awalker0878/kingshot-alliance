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
             |          |          |
             |          |          +--> Shared/Infrastructure
             |          +-------------> Workflows for true multi-owner commands
             +------------------------> ReadModels for composed reads
```

## Bounded contexts

| Context | Primary responsibility |
| --- | --- |
| Accounts | User identity, registration, authentication, credentials, verification, profile and MFA. |
| GameWorld | Player/Kingdom identity, placement/reference facts, Kingdom governance and Kingdom transfers. |
| Alliance | Alliance lifecycle, membership/leadership, Alliance access, recruitment and content. |
| Operations | Event execution, participation, polls, rosters, battle plans, rallies, King Perks and results. |
| Intelligence | Observed/ingested facts, roster/contribution intelligence, diplomacy and sharing. Cross-context Event analysis is a ReadModel composition concern. |
| Communications | Generic notification delivery, preferences, channels, retries and idempotency. |
| Platform | Platform administration, Alliance platform administration, data governance, Event administration and integrations. |

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

## Cross-context commands

A caller does not manipulate another context's Eloquent models. It calls an explicit owner Action/Query and passes stable scalar identifiers such as `user_id`, `player_id`, `alliance_id` and `kingdom_id`.

A command requiring more than one write owner is coordinated through a Workflow. V3 keeps Workflows intentionally small:

- `AccountOnboarding`
- `ExternalEventParticipation`
- `KingdomGovernance`

Player activation belongs to `GameWorld/Players`; Kingdom transfer belongs to `GameWorld/KingdomTransfers`.

## Read composition

`app/ReadModels` may combine data from several contexts for a projection. ReadModels are read-only and do not become persistence owners.

## Shared infrastructure

`app/Shared/Infrastructure` contains generic infrastructure such as audit, outbox/messaging and runtime mechanics. It owns no game or platform business policy.
