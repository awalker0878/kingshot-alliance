# Repository structure audit

[← Product and program documentation](README.md)

**Document type:** Repository architecture evidence  
**Status:** Current migration audit — final validated SHA to be recorded after protected checks  
**Prior runtime audited at:** `b908407b68f2567ebcd5b9e43ebf1d842844b20a`  
**Prior audit date:** 2026-08-08

## Purpose

This document records the repository's canonical physical structure after the completed Phase 0–6 domain-first refactor, accepted Kingdoms increments, repository-controlled production hardening, and the domain-documentation folder migration.

It preserves the intent and evidence of the earlier `docs/domains/repository-structure-audit.md` while updating assumptions that became stale: Kingdoms now owns accepted runtime PHP and `docs/domains/` now mirrors `app/Domain/` one-for-one with domain folders rather than flat living guides.

Normative sources remain the [implementation plan](implementation-plan.md), [documentation standard](documentation-standard.md), accepted ADRs, and architecture tests. This audit records conformance evidence; it does not create a second architecture definition.

## Canonical application structure

Runtime PHP is owned under `app/Domain/<CanonicalDomain>`.

Canonical roots are:

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

All canonical roots are present. Unlike the 2026-08-08 pre-Kingdoms runtime audit, `Kingdoms` now owns accepted runtime PHP for `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`.

Former layer-first roots `app/Application`, `app/Http`, `app/Infrastructure`, `app/Models`, `app/Providers`, and catch-all `app/Domain/Shared` are not part of the accepted structure.

These application ownership invariants are enforced by the Architecture test suite, including `tests/Architecture/DomainStructureTest.php` and Kingdoms-specific structure tests.

## Canonical documentation structure

`docs/` contains exactly five approved top-level documentation groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Each group has `README.md` navigation.

`docs/domains/` now has this deterministic structure:

```text
docs/domains/
  README.md
  alliances/README.md
  audit/README.md
  authorization/README.md
  content/README.md
  contributions/README.md
  events/README.md
  identity/README.md
  integrations/README.md
  kingdoms/README.md
  memberships/README.md
  notifications/README.md
  platform/README.md
  rallies/README.md
  recruitment/README.md
```

Capability documents live inside the owning domain folder. For example:

```text
docs/domains/kingdoms/
  README.md
  roster.md
  snapshots.md
  intelligence.md
  csv-migration.md
  transfer-planning.md
  alliance-intelligence.md
```

There are no canonical flat living-domain contracts directly under `docs/domains/`. The root `README.md` is the only Markdown root file after migration.

Parallel legacy structures such as `docs/architecture/`, top-level `docs/runbooks/`, or flat phase documents at `docs/` root remain prohibited.

These documentation invariants are enforced by `tests/Architecture/RepositoryStructureTest.php`.

## Code-to-documentation parity

The domain mapping is bidirectional:

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

After lowercase/kebab normalization:

- every first-level code domain must have one docs-domain folder;
- every docs-domain folder must correspond to one code domain; and
- every docs-domain folder must contain `README.md`.

Adding a code domain without docs, or docs without code, is an architecture-test failure.

## Canonical test structure

Accepted test roots remain:

```text
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Architecture/repository tests protect physical layout while feature, integration, performance, tenant-isolation, and unit suites validate runtime behavior.

## Current structural assertions

The repository is expected to satisfy these architecture-level conditions:

1. Every implementation-plan domain directory exists.
2. Runtime PHP under `app/` is owned by a canonical domain.
3. Layer-first legacy application directories are absent.
4. `app/Domain/Shared` is absent.
5. Accepted Kingdoms runtime remains under `app/Domain/Kingdoms`.
6. Phase 6 Integrations runtime remains under `app/Domain/Integrations`.
7. Documentation uses only the five top-level groups and every group has a navigation index.
8. `docs/domains/*` directory names match normalized `app/Domain/*` roots exactly.
9. Every docs-domain directory contains `README.md`.
10. No flat living Markdown file remains directly under `docs/domains/` other than `README.md`.
11. Capability filenames use lowercase kebab-case inside the owning domain folder.
12. Local Markdown links resolve.
13. Tests use only the six canonical test groups.

## Preserved 2026-08-08 audit context

The prior audit established these important facts after the original domain-first refactor:

- runtime PHP belonged under canonical domain roots rather than layer-first `app/Application`, `app/Http`, `app/Infrastructure`, `app/Models`, or `app/Providers`;
- `app/Domain/Shared` was prohibited;
- documentation used the five canonical top-level groups;
- tests used the six canonical test groups; and
- structure was enforced by `DomainStructureTest`, `DomainBoundaryTest`, and `RepositoryStructureTest`.

At that time Kingdoms was intentionally documentation-only and domain documentation was flat. Those two statements are historical context, not current architecture: K1–K3 subsequently implemented Kingdoms runtime, and this migration replaced flat domain guides with mirrored folders.

## Validation and maintenance

Primary automated evidence is:

- `tests/Architecture/DomainStructureTest.php`;
- `tests/Architecture/DomainBoundaryTest.php`;
- `tests/Architecture/RepositoryStructureTest.php`;
- Kingdoms-specific Architecture structure/acceptance tests; and
- the protected CI workflow that runs the architecture suite and documentation-link checks.

When physical structure changes intentionally:

1. update the implementation plan/documentation standard or applicable ADR first;
2. change architecture tests with the implementation;
3. migrate/update all affected documentation links in the same change; and
4. refresh this audit against the exact validated source commit.

Do not preserve obsolete directories, duplicate documentation trees, redirect/stub compatibility files, or stale flat guides merely to keep historical paths working. Code/tests remain authoritative for implemented runtime structure.
