# Shared infrastructure

Status: Current — Architecture V3

`app/Shared/Infrastructure` contains reusable technical infrastructure only. It is not a business context and does not own game or platform policy.

## Appropriate Shared concerns

Examples include:

- audit mechanics;
- transactional outbox/messaging transport;
- generic runtime health/readiness mechanics;
- generic metrics/telemetry mechanics;
- generic security/HTTP infrastructure;
- framework/provider glue that has no business vocabulary.

## Boundary rules

Shared must not:

- import a business context;
- define Alliance, Kingdom, Event, King Perk, Intelligence or Platform business policy;
- own game permissions;
- persist business aggregates;
- become a dumping ground for code with unclear ownership.

If code contains business language, rules or state, identify the owning context and capability instead.