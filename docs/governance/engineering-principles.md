# Engineering principles

Status: Current

## Ownership before implementation

Identify the owning bounded context/capability before choosing a namespace, table or endpoint. Do not create a context for every noun.

## Player-scoped game authority

User authenticates the account. Active Player is the game-domain principal. Never aggregate privileges across all Players owned by a User. Platform Administrator is platform authority only.

## Explicit write boundaries

Controllers stay thin. Owning actions/services enforce invariants and transaction-time mutation authority. Multi-context writes use explicit Workflows; composed reads use ReadModels.

## No persistence reach-through

A shared database is not permission to mutate another context's aggregates. Use supported application/query/event contracts.

## Transactional side effects

Persist durable outbox intent with the business transaction where required. Execute remote/retryable side effects after commit and design for at-least-once delivery.

## Operational correctness

Treat migrations, recovery, observability, queue behavior and configuration as part of feature quality—not post-release cleanup.

## Security and privacy

Fail closed at privileged boundaries, keep secrets out of source/logs/evidence, minimize sensitive data and keep real infrastructure evidence separate from repository claims.

## Delete superseded structure

When Architecture V2 replaces a V1 code/documentation path, remove the superseded path rather than adding permanent compatibility scaffolding unless a deliberate compatibility contract is required.