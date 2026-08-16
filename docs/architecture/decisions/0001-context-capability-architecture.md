# ADR 0001: Context-and-capability architecture

Status: Accepted

## Decision

Organize business behavior into seven bounded contexts under `app/Contexts`: Accounts, GameWorld, Alliance, Operations, Intelligence, Communications and Platform. Capabilities are modules inside those contexts.

Cross-context read composition uses `app/ReadModels`; cross-context mutation orchestration uses `app/Workflows`; technical cross-cutting infrastructure uses `app/Shared`.

## Rationale

Context-first ownership keeps cohesive business language, persistence, policy and consistency rules together. Implementation folders remain subordinate to those ownership boundaries rather than defining them.

## Consequences

- a new model, table or route does not imply a new context;
- context ownership is decided before implementation placement;
- cross-context collaboration uses explicit composition or durable contracts;
- architecture documentation is organized by context/capability, while general documentation is organized by reader intent.
