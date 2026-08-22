# ADR-0010: Separate screenshot evidence from domain results

Status: Accepted — 2026-08-22

## Context

KingShot screenshots can reduce manual data entry, but uploaded evidence, machine interpretation and accepted domain facts have different ownership and trust semantics. Putting screenshots inside every destination capability would duplicate storage/classification/review infrastructure. Letting a generic ingestion capability own the resulting Event/Player/Alliance facts would violate V3 owner boundaries and make provenance indistinguishable from authoritative state.

The first concrete use case is Bear Hunt battle reports. `Operations/Results` already owns Event results and Bear Hunt damage semantics; `Intelligence` already owns observational ingestion/provenance concepts.

## Decision

Create `Intelligence/Evidence` as a capability inside the existing Intelligence context. It owns uploaded game evidence, immutable provenance, classification/extraction attempts, field confidence, review/corrections, duplicate decisions, commit attempts and retention.

Evidence does not own inferred domain data. A reviewed revision is committed through `app/Workflows` into a scalar owner Action. For Bear Hunt, `Operations/Results` owns an immutable accepted report ledger and recomputes its existing result aggregates from accepted reports.

The cross-context handshake is:

`Evidence begin commit → Workflow build reviewed scalar command → destination owner Action → Evidence record destination receipt`.

The destination uses a stable idempotency key so a retry after interrupted acknowledgement cannot duplicate the accepted fact.

Upload scanning is moved to `Shared/Infrastructure/Uploads` so Evidence and Alliance Content share the technical security mechanism without creating an Intelligence → Alliance dependency.

## Consequences

- Screenshot provenance remains inspectable independently of the resulting domain record.
- Machine confidence and human correction history cannot be mistaken for authoritative domain state.
- New evidence types can reuse the intake pipeline while still committing to different owners.
- Domain owners remain responsible for current authorization, invariants, idempotency and correction semantics.
- Evidence deletion cannot cascade into committed domain history.
- The Bear Hunt destination needs an owner-local report ledger rather than treating each screenshot as a direct overwrite/increment of `EventPlayerResult`.

## Rejected alternatives

### Evidence owns extracted domain facts

Rejected because it duplicates Operations/GameWorld/Alliance truth and breaks bounded-context ownership.

### Put screenshot handling directly in Operations/Results

Rejected because classification/extraction/review/provenance is reusable evidence behavior, not Bear Hunt result semantics.

### Reuse Alliance/Content media objects directly

Rejected because Alliance Content owns publishable Alliance media. Game evidence has different lifecycle, retention, provenance and review semantics. Only the technical upload scanner is shared.

### Distributed transaction across Evidence and Operations

Rejected because the contexts must remain independently authoritative. Stable idempotency plus destination receipts provides explicit crash recovery without cross-context persistence ownership.
