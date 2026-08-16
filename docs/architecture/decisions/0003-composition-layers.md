# ADR 0003: Workflows, ReadModels and Shared composition layers

Status: Accepted

## Decision

Use three explicit non-context layers:

- `app/Workflows` for multi-context command orchestration;
- `app/ReadModels` for cross-context read composition;
- `app/Shared` for business-neutral technical contracts/infrastructure.

## Rationale

Not every cross-cutting use case belongs to a new bounded context. Explicit composition layers allow collaboration without transferring aggregate ownership or creating a generic service layer that owns everything.

## Consequences

Workflows do not own participating persistence. ReadModels are read-only. Shared cannot import business contexts or contain gameplay/alliance policy.