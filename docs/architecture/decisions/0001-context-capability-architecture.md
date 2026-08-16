# ADR 0001: Context-and-capability architecture

Status: Accepted

## Decision

Organize business behavior into seven bounded contexts under `app/Contexts`: Accounts, GameWorld, Alliance, Operations, Intelligence, Communications and Platform. Capabilities are modules inside those contexts.

Cross-context read composition uses `app/ReadModels`; cross-context mutation orchestration uses `app/Workflows`; technical cross-cutting infrastructure uses `app/Shared`.

## Rationale

The previous noun/domain-per-package model encouraged accidental architectural boundaries around entities and implementation folders. Context-first ownership keeps related capabilities together and makes persistence, policy and dependency direction clearer.

## Consequences

- a new model/table/route does not imply a new context;
- context ownership must be decided before implementation placement;
- V2 code does not import legacy `App\Domain\*` classes;
- architecture documentation is organized by context/capability, while general documentation is organized by reader intent.