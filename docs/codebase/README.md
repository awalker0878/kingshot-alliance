# Codebase

Status: Current — Architecture V3

Codebase documentation maps the V3 business architecture to physical source structure and implementation rules.

Start with:

- [Source layout](source-layout.md)
- [Module map](module-map.md)
- [Actor context](actor-context.md)
- [Request lifecycle](request-lifecycle.md)
- [Routing and HTTP](routing-and-http.md)
- [Persistence](persistence.md)
- [Transactions and locking](transactions-and-locking.md)
- [Workflows](workflows.md)
- [ReadModels](read-models.md)
- [Shared infrastructure](shared-infrastructure.md)
- [Events, outbox and jobs](events-outbox-and-jobs.md)
- [Testing](testing.md)

## V3 physical principle

The source tree exposes business architecture directly:

```text
app/Contexts/<Context>/<Capability>/...
```

Technical layers such as `Actions`, `Models`, `Queries`, `Services`, `Policies` and `Http` belong inside capabilities, not at the context root.

`app/Workflows` contains only true multi-context commands. `app/ReadModels` is read-only composition. `app/Shared/Infrastructure` contains business-neutral infrastructure.

The target tree is authoritative even while implementation refactoring is in progress; current source that violates the target is migration work, not an alternative supported architecture.