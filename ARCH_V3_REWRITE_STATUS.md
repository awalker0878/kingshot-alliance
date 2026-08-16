# Architecture V3 rewrite status

Branch: `architecture-v3-capability-alignment`

## Overall status

In progress.

## Phase CA-P0 — Establish architecture rules

Status: IN PROGRESS

### Done
- Architecture V3 documentation established under `/docs`.
- Capability-first target tree defined.
- No compatibility/shim policy confirmed for rewrite.
- New implementation tests will be created only under `tests/v3`.

### Remaining
- Inspect current `/app`, routes, providers, migrations, and existing tests for physical ownership/leakage before code movement.
- Confirm exact source files to move/delete in CA-P1.

### Failed / blocked
- None.

## Phase CA-P1 — Capability-first physical reorganization

Status: NOT STARTED

## Phase CA-P2 — Thin HTTP adapters

Status: NOT STARTED

## Phase CA-P3 — Context-owned write APIs

Status: NOT STARTED

## Phase CA-P4 — Workflow correction

Status: NOT STARTED

## Phase CA-P5 — Communications cleanup

Status: NOT STARTED

## Phase CA-P6 — Architecture enforcement

Status: NOT STARTED

## Phase CA-P7 — Full leakage scan and certification

Status: NOT STARTED
