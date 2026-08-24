# Architecture

Status: Current — Architecture V3

Kingshot Alliance is organized around **bounded contexts containing business capabilities**. The physical source tree is capability-first so ownership is visible directly in the codebase.

Start with:

- [System overview](system-overview.md)
- [Context map](context-map.md)
- [Capability map](capability-map.md)
- [Authority model](authority-model.md)
- [Data ownership](data-ownership.md)
- [Consistency and transactions](consistency-and-transactions.md)
- [Integration model](integration-model.md)
- [Alliance Assistant composition](alliance-assistant.md)
- [Architecture decision records](adr/README.md)
- [Bounded contexts](contexts/README.md)
- [Architecture compliance](../governance/architecture-compliance.md)

## V3 architecture rules

### Context

A bounded context owns a distinct business language, policy and consistency boundary.

### Capability

A capability is a cohesive area of business behavior inside a context. Capabilities are the primary physical modules under each context.

### Technical layer

Actions, Models, Queries, Services, Access, Http, Events and similar folders are implementation details **inside a capability**. They are not peer architecture modules at the context root.

### Workflow

A Workflow coordinates a command that genuinely spans multiple context owners. It owns process behavior, not business persistence, permission vocabulary or domain models.

### ReadModel

A ReadModel combines data from multiple owners for a read use case. It owns no writes.

### Shared

Shared contains business-neutral technical infrastructure only.

## Physical rule

The following context-root technical buckets are not valid V3 structure:

```text
app/Contexts/<Context>/Actions
app/Contexts/<Context>/Models
app/Contexts/<Context>/Queries
app/Contexts/<Context>/Services
app/Contexts/<Context>/Policies
app/Contexts/<Context>/Http
```

Technical layers belong under an owning capability:

```text
app/Contexts/<Context>/<Capability>/Actions
app/Contexts/<Context>/<Capability>/Models
app/Contexts/<Context>/<Capability>/Queries
app/Contexts/<Context>/<Capability>/Services
app/Contexts/<Context>/<Capability>/Http
```

A capability does not need every technical subfolder.

## Cross-context rule

One context owns each business write. Cross-context callers use explicit owner Actions/Queries, scalar identifiers, durable events, Workflows or ReadModels as appropriate. A shared database does not permit persistence reach-through.
