# Repository structure audit

[← Domain documentation](README.md)

**Status:** Current  
**Runtime audited at:** `b908407b68f2567ebcd5b9e43ebf1d842844b20a`  
**Audit date:** 2026-08-08

## Purpose

This document records the current repository structure after completion of Phases 0–6, the domain-first architecture refactor, and repository-controlled production hardening. It replaces the earlier pre-refactor inventory that described the old layer-first application and flat documentation layout.

The normative sources remain the [implementation plan](../product/implementation-plan.md), accepted ADRs, and architecture tests. This audit is evidence that the repository currently conforms to those sources; it does not create a second architecture definition.

## Canonical application structure

Runtime PHP is owned under `app/Domain/<CanonicalDomain>`.

The canonical domain roots are:

- `Alliances`
- `Audit`
- `Authorization`
- `Content`
- `Contributions`
- `Events`
- `Identity`
- `Integrations`
- `Kingdoms`
- `Memberships`
- `Notifications`
- `Platform`
- `Rallies`
- `Recruitment`

All canonical roots are present. `Kingdoms` is intentionally documentation-only until additional game/kingdom reference capability is explicitly approved. The remaining domains own runtime PHP appropriate to the completed Phase 0–6 scope; Phase 6 specifically includes runtime API-credential and webhook implementation under `Integrations`.

The former layer-first application roots `app/Application`, `app/Http`, `app/Infrastructure`, `app/Models`, and `app/Providers` are not part of the accepted structure. The former catch-all `app/Domain/Shared` root is also prohibited.

These invariants are enforced by `tests/Architecture/DomainStructureTest.php`.

## Canonical documentation structure

`docs/` contains exactly the five approved top-level documentation groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Each group has a `README.md` navigation index. Descriptive Markdown filenames use lowercase kebab-case; numbered ADR filenames and directory `README.md` files are the documented exceptions.

Parallel legacy structures such as `docs/architecture/`, `docs/runbooks/`, or a flat collection of phase documents at the `docs/` root are not part of the current repository model.

These invariants are enforced by `tests/Architecture/RepositoryStructureTest.php`.

## Canonical test structure

The accepted test roots are:

```text
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Architecture and repository-structure tests protect the physical layout while feature, integration, performance, tenant-isolation, and unit suites validate runtime behavior.

## Current structural assertions

The repository currently satisfies these architecture-level conditions:

1. Every implementation-plan domain directory exists.
2. Runtime PHP under `app/` is owned by a canonical domain.
3. Layer-first legacy application directories are absent.
4. `app/Domain/Shared` is absent.
5. `Kingdoms` contains no runtime PHP until approved capability exists.
6. Phase 6 `Integrations` runtime is present.
7. Documentation uses only the five canonical groups and each group has a navigation index.
8. Descriptive documentation filenames use the documented lowercase kebab-case convention.
9. Tests use only the six canonical test groups.

## Validation and maintenance

The primary automated evidence is:

- `tests/Architecture/DomainStructureTest.php`
- `tests/Architecture/DomainBoundaryTest.php`
- `tests/Architecture/RepositoryStructureTest.php`

When physical structure changes intentionally, update the implementation plan or applicable ADR first, change the architecture tests with the implementation, and then refresh this audit against the exact validated source commit.

Do not preserve obsolete directories, duplicate documentation trees, or compatibility shims merely to keep this audit unchanged. Code and tests remain authoritative for implemented runtime structure.
