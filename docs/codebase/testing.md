# Testing

Status: Current

Architecture V2 uses a **clean-room test suite** under `tests/v2`. The previous Architecture/Feature/Integration/Performance/TenantIsolation/Unit taxonomy is not retained as an acceptance model.

## Sources of truth

New tests are authored from the current code/database constraints and `/docs`, in this order:

1. executable code and database constraints for exact runtime behavior;
2. architecture documentation for ownership, invariants and supported collaboration;
3. codebase documentation for physical implementation locations;
4. product documentation for user outcomes;
5. governance/reference/operations documentation for cross-cutting requirements.

Historical tests are not specifications and are not migrated into V2.

## Structure

- all executable PHP tests live below `tests/v2`;
- every PHP test file/class ends in `V2Test`;
- support code lives under `tests/v2/Support` and is not a test;
- visual tests live under `tests/v2/Visual` and use `V2` in their spec names;
- `phpunit.xml` exposes one `Architecture V2` suite rooted at `tests/v2`.

## Coverage model

Every documented capability has a dedicated V2 surface contract that verifies current documentation, autoloadable implementation symbols, persistence mappings and public application surfaces. Separate behavior tests protect high-risk business/security invariants.

Protected mutations must cover authorization failure, scope isolation and transaction/locking behavior where relevant. Cross-context behavior must use supported context contracts, Workflows, ReadModels or durable messaging rather than direct persistence reach-through.

## Required verification

The V2 gate uses PostgreSQL, `migrate:fresh`, Pint, Larastan and the complete `tests/v2` suite. It also verifies that `tests` contains no legacy test tree and that `App\\Domain` cannot return in runtime or tests.
