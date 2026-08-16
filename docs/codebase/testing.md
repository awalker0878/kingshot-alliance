# Testing

Status: Current

Architecture V2 uses one verification suite under `tests/v2`.

## Sources of truth

Tests are authored from current contracts in this order:

1. executable code and database constraints for exact runtime behavior;
2. architecture documentation for ownership, invariants and supported collaboration;
3. codebase documentation for physical implementation locations;
4. product documentation for user outcomes;
5. governance, reference and operations documentation for cross-cutting requirements.

## Structure

- all executable PHP tests live below `tests/v2`;
- every PHP test file/class ends in `V2Test`;
- support code lives under `tests/v2/Support` and is not a test;
- visual tests live under `tests/v2/Visual` and use `V2` in their spec names;
- `phpunit.xml` exposes one `Architecture V2` suite rooted at `tests/v2`.

## Coverage model

Every owned implementation surface has an executable contract that verifies documentation, autoloadable symbols, persistence mappings or public application behavior as appropriate. Behavior tests protect high-risk identity, authority, isolation, transaction and policy invariants.

The nine architecture compliance contracts are defined in [Architecture V2 compliance](../governance/architecture-compliance.md) and enforced by `ArchitectureComplianceV2Test` together with focused context/workflow tests.

Protected mutations cover authorization failure, scope isolation and transaction/locking behavior where relevant. Cross-context behavior uses supported context contracts, Workflows, ReadModels or durable messaging rather than persistence ownership leakage.

## Required verification

The Architecture V2 gate uses PostgreSQL 18, `migrate:fresh`, Pint, Larastan and the complete `tests/v2` suite, plus frontend checks and visual-test discovery.
