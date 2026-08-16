# Kingshot Alliance documentation

Status: Current — Architecture V3

This documentation describes the current intended architecture and operating model. It does not preserve superseded architecture descriptions; repository history provides change history.

## Documentation areas

| Area | Purpose |
| --- | --- |
| [Architecture](architecture/README.md) | Bounded contexts, capabilities, ownership, authority, consistency and integration boundaries. |
| [Codebase](codebase/README.md) | Physical source layout and implementation rules. |
| [System operations](operations/README.md) | Deployment, runtime, monitoring and recovery. |
| [Product](product/README.md) | User-facing capabilities and terminology. |
| [Governance](governance/README.md) | Engineering, security, verification and production rules. |
| [Reference](reference/README.md) | Lookup-oriented permissions, events, configuration, routes and API facts. |

## Architecture V3

Architecture V3 is capability-first inside seven bounded contexts:

- Accounts
- GameWorld
- Alliance
- Operations
- Intelligence
- Communications
- Platform

The source tree is intentionally shaped so the business architecture is visible from `app/Contexts`.

`app/Workflows` contains only true cross-context command orchestration. `app/ReadModels` contains read-only cross-context composition. `app/Shared` contains business-neutral infrastructure.

The canonical V3 source tree is defined in [Source layout](codebase/source-layout.md), and the capability ownership map is defined in [Capability map](architecture/capability-map.md).

## Source-of-truth rule

Architecture documentation defines intended ownership and boundaries. Executable code and database constraints must implement those contracts. When implementation and documentation disagree, the change is incomplete until they are aligned.