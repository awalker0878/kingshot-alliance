# Architecture

Status: Current

This area describes the logical architecture and business ownership model of Kingshot Alliance. It answers what the system is, why boundaries exist, who owns business rules and data, and how contexts collaborate.

The source tree implements this architecture; it does not define the architecture. Start with:

- [System overview](system-overview.md)
- [Context map](context-map.md)
- [Capability map](capability-map.md)
- [Authority model](authority-model.md)
- [Data ownership](data-ownership.md)
- [Consistency and transactions](consistency-and-transactions.md)
- [Integration model](integration-model.md)
- [Bounded contexts](contexts/README.md)
- [Architecture decisions](decisions/README.md)
- [Architecture compliance](../governance/architecture-compliance.md)

## Architecture V2 rules

1. Business behavior is organized into bounded contexts containing cohesive capabilities.
2. A model, table, route, controller or folder is not evidence that a new bounded context is required.
3. Write ownership belongs to one context. Other contexts collaborate through deliberate application contracts, workflow orchestration, read models or durable events.
4. Cross-context read composition belongs in `app/ReadModels` when no single write-owning context naturally owns the view.
5. Cross-context mutation orchestration belongs in `app/Workflows`; a workflow never becomes persistence owner of participating aggregates.
6. `app/Shared` contains only business-neutral technical contracts and infrastructure.
7. Runtime dependencies follow the canonical context, composition and shared-infrastructure boundaries defined here.

For physical implementation details, use [Codebase documentation](../codebase/README.md).
