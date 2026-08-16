# Definition of Done

Status: Current

A change is done only when applicable product, architecture, quality, security and operational obligations are satisfied.

## Scope and outcome

- intended user/operator outcome and acceptance criteria are clear;
- new capability is intentional rather than accidentally created by a model/table/folder;
- deferred behavior is explicit rather than half-implemented.

## Architecture

- owning context/capability is identified;
- active Player/User/Platform authority model remains correct;
- no unsupported cross-context persistence mutation is introduced;
- multi-owner mutations use an appropriate Workflow;
- multi-owner read composition uses a ReadModel where appropriate;
- Shared remains business-neutral;
- material architecture changes update living architecture and add/supersede a decision record where justified.

## Data and concurrency

- migrations have forward and recovery implications understood;
- database constraints protect critical persistence invariants where appropriate;
- mutable write authority is revalidated transactionally where required;
- lock ordering/concurrency/idempotency risks are addressed;
- historical identity/ownership is not silently rewritten by current membership/placement.

## Quality

- success/failure/authorization/scope/concurrency/retry behavior is tested as applicable;
- formatting, static analysis, type checks and production frontend build pass;
- relevant Architecture V2 verification suites remain green;
- dependency/code/container security checks pass where required.

## Security and privacy

- authorization, sensitive data, secrets/tokens, destructive actions, retention and external trust boundaries are addressed;
- no secret/recovery material is committed to code/docs/evidence;
- repository checks are not represented as proof of external infrastructure controls.

## Operations

- configuration, logs/metrics, queue behavior, deployment, migration, rollback and recovery changes are documented where materially affected;
- new dependencies have health/ownership/recovery expectations;
- alerts map to useful runbooks when operational action is required.

## Product and frontend

- user-facing loading/empty/error/accessibility/responsive/localization impacts are handled as applicable;
- Product capability/terminology documentation changes only when the user contract changes materially.

## Documentation

- follow [Documentation standard](documentation-standard.md);
- update the authoritative document instead of duplicating a rule;
- internal links resolve;
- obsolete live documentation is removed rather than kept as a parallel legacy tree;
- Reference catalogues are updated when their lookup facts materially change.

## Acceptance

- required CI/review findings are resolved on the final intended head;
- production cutover remains governed by [Production approval](production-approval.md), not inferred from feature completion.