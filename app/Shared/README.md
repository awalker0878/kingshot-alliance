# V2 shared kernel

Only genuinely cross-cutting technical contracts and infrastructure belong here. Infrastructure packages live below `App\Shared\Infrastructure` (for example AuditTrail and Messaging/Outbox); access primitives and other tiny shared contracts may remain directly under Shared when they are not business capabilities.

`Shared` must not import any business context, workflow, read model, or `App\Domain\*` class. No feature aggregate or gameplay/alliance policy belongs here.
