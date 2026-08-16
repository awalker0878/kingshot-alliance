# Shared infrastructure

Status: Current

`app/Shared` is the technical shared kernel. It contains only genuinely cross-cutting infrastructure/contracts that do not carry business ownership.

Current structure includes:

- `Access` — small cross-cutting access primitives;
- `Http` — technical HTTP helpers/contracts;
- `Infrastructure/AuditTrail` — generic audit mechanics;
- `Infrastructure/Messaging` — generic messaging/outbox infrastructure;
- `Providers` — shared technical providers.

## Dependency rule

Shared must not import a business context, workflow, ReadModel or legacy `App\Domain\*` class. No gameplay, Alliance, Event, Kingdom or product policy belongs here.

If a shared helper starts needing business nouns or context-specific permissions, it probably belongs back in the owning context.