# System overview

Status: Current

Kingshot Alliance is a modular Laravel application for coordinating KingShot players, alliances, kingdoms and operational game activities. The architecture separates account identity, game-world identity, alliance operations, live event operations, intelligence/analytics, communications delivery and platform administration.

## Logical layers

```text
Browser / API / Console
        |
        v
Application entry points
        |
        +------------------------------+
        | write-owning bounded contexts|
        | Accounts                     |
        | GameWorld                    |
        | Alliance                     |
        | Operations                   |
        | Intelligence                 |
        | Communications               |
        | Platform                     |
        +------------------------------+
             |          |          |
             |          |          +--> Shared technical infrastructure
             |          +-------------> Cross-context Workflows
             +------------------------> ReadModels for composed reads
```

## Write-owning contexts

| Context | Primary responsibility |
| --- | --- |
| Accounts | User account identity, authentication and account security. |
| GameWorld | Neutral Player/Kingdom identity, placement and Kingdom governance facts. |
| Alliance | Alliance tenant lifecycle, membership, Alliance authority, recruitment and Alliance content. |
| Operations | Execution-time game coordination: Events, participation, rosters, battle plans, rallies, results, King Perks and reminder policy. |
| Intelligence | Observational/analytical facts, ingestion, roster intelligence, contribution history, event analysis, diplomacy and sharing. |
| Communications | Delivery coordination, recipient preferences, retry/idempotency and channel behavior. |
| Platform | Cross-tenant SaaS administration, lifecycle controls, platform grants, event-type administration and external integrations. |

## Composition layers

`app/ReadModels` may read from several contexts to produce user-facing projections. It is read-only and cannot transfer aggregate ownership.

`app/Workflows` coordinates multi-context mutations such as Player context, registration, Kingdom governance and Kingdom transfer. It invokes supported context contracts; it does not own the participating aggregates.

`app/Shared` provides technical infrastructure such as audit trail and transactional messaging/outbox. Business policy must not migrate into Shared.

## Important naming distinction

`app/Contexts/Operations` is a **business bounded context** for game/event operations. `docs/operations` is **system operations documentation** for deployment, observability and recovery. They intentionally answer different questions.