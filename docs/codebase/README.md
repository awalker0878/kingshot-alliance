# Codebase

Status: Current

This area explains **how the architecture is implemented in this repository**. Use it when you need to locate code, understand request flow, persistence, transactions, jobs, frontend structure or tests.

Architecture ownership is documented separately under [Architecture](../architecture/README.md). A source folder is not allowed to redefine a bounded context merely by existing.

Start with:

- [Source layout](source-layout.md)
- [Module map](module-map.md)
- [Request lifecycle](request-lifecycle.md)
- [Actor context](actor-context.md)
- [Routing and HTTP](routing-and-http.md)
- [Persistence](persistence.md)
- [Transactions and locking](transactions-and-locking.md)
- [Events, outbox and jobs](events-outbox-and-jobs.md)
- [Workflows](workflows.md)
- [Read models](read-models.md)
- [Shared infrastructure](shared-infrastructure.md)
- [Frontend](frontend.md)
- [Testing](testing.md)
- [Local development](local-development.md)

## Codebase rule

The physical source layout implements the business architecture but is allowed to contain technical composition layers that are not bounded contexts. Do not infer business ownership from namespace depth alone.