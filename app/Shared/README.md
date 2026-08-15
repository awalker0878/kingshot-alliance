# V2 shared kernel

Only genuinely cross-cutting technical contracts/infrastructure belong here: access primitives, audit recording, messaging/outbox, clock/time, IDs and transaction helpers.

`Shared` must not import any business context, workflow, read model, or `App\Domain\*` class. No feature aggregate belongs here.