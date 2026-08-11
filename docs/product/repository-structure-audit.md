# Repository structure audit

[← Product and program documentation](README.md)

**Document type:** Current repository architecture evidence  
**Status:** Current  
**P6 baseline audited from:** P5 final transition `983b662bac8873ba2eb71ccec8a6c9e5d1331923`  
**Audit refresh:** `DCP-P6`

## Purpose

This audit records current physical repository/documentation structure after the accepted Phase 0–6 domain-first refactor, Kingdoms K1–K3 increments, production hardening, and DCP P1–P5 documentation completion.

Normative owners remain the [implementation plan](implementation-plan.md), [documentation standard](documentation-standard.md), accepted [ADRs](../adr/README.md), [architecture-governance standard](architecture-governance-standard.md), and architecture tests. This file is conformance evidence, not a parallel architecture definition.

## Canonical application structure

Runtime PHP is owned under `app/Domain/<CanonicalDomain>` with exactly:

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

Former layer-first roots `app/Application`, `app/Http`, `app/Infrastructure`, `app/Models`, `app/Providers`, and catch-all `app/Domain/Shared` are not part of the accepted architecture.

Kingdoms is a full runtime domain through accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`; the pre-Kingdoms documentation-only state is historical only.

## Canonical documentation structure

`docs/` has exactly five approved top-level groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Each group owns a distinct concern and has `README.md` navigation.

`docs/domains/` mirrors code ownership one-for-one:

```text
docs/domains/
  README.md
  alliances/
  audit/
  authorization/
  content/
  contributions/
  events/
  identity/
  integrations/
  kingdoms/
  memberships/
  notifications/
  platform/
  rallies/
  recruitment/
```

The root `docs/domains/README.md` is the only Markdown file directly under `docs/domains/`; living capability/evidence files are nested beneath their owner.

Every canonical domain now has five deterministic living profile paths where applicable to P1–P5 governance:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
docs/domains/<domain>/testing/README.md
```

Domain-specific product/acceptance evidence may additionally live under `docs/domains/<domain>/product/`.

Parallel legacy structures such as `docs/architecture/`, top-level `docs/runbooks/`, flat domain living files, or layer-first runtime roots remain prohibited.

## Code-to-documentation parity

The canonical mapping is bidirectional:

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

After lowercase/kebab normalization:

- every code domain has one docs-domain directory;
- every docs-domain directory maps to one code domain;
- every docs-domain directory has its living contract; and
- P1–P5 architecture tests protect required specialized profile parity.

## Canonical test structure

Accepted test roots are exactly:

```text
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Architecture tests protect ownership/structure/documentation rules; other suites validate runtime behavior and evidence classes.

## Current structural assertions

The current repository is expected to satisfy:

1. all 14 implementation-plan/domain roots exist;
2. runtime PHP under `app/` is domain-owned;
3. layer-first legacy application directories are absent;
4. `app/Domain/Shared` is absent;
5. Kingdoms runtime remains under `app/Domain/Kingdoms`;
6. Integrations runtime remains under `app/Domain/Integrations`;
7. documentation uses only the five top-level groups and each has navigation;
8. `docs/domains/*` directory names match normalized `app/Domain/*` roots exactly;
9. each domain has its required P1–P5 living profile set;
10. no flat living domain Markdown remains at the `docs/domains/` root;
11. living filenames follow the repository naming rules;
12. local Markdown links resolve;
13. tests use only the six canonical test groups; and
14. current architecture/governance surfaces remain under `docs/adr/` and `docs/product/`, not in a new parallel architecture tree.

## Shared documentation ownership result

P6 confirms the shared roots remain correctly scoped:

- `docs/product/` — cross-program governance/current-state navigation, architecture audits, historical phase-wide acceptance, production decisions, DCP standards/evidence;
- `docs/security/` — shared security baseline, historical phase-wide threat evidence, production security boundary;
- `docs/operations/` — shared runtime/deployment/observability/recovery/runbooks and historical phase-wide operating evidence;
- `docs/adr/` — durable architecture decisions and current system architecture index.

No additional domain-specific relocation is required by P6. Domain-owned living detail remains beneath `docs/domains/<domain>/`.

## Preserved historical context

The 2026-08-08 audit established the original domain-first structure before Kingdoms runtime existed and before domain documentation moved from flat guides into mirrored folders. Those facts remain useful historical context but are not current architecture.

P6 does not rewrite accepted historical evidence; it replaces migration-era **current audit status** with this current system view.

## Validation and maintenance

Primary executable evidence includes:

- `tests/Architecture/DomainStructureTest.php`;
- `tests/Architecture/DomainBoundaryTest.php`;
- `tests/Architecture/RepositoryStructureTest.php`;
- P1–P5 documentation architecture suites;
- Kingdoms structure/acceptance architecture tests; and
- P6 architecture-governance validation.

When physical structure changes intentionally:

1. change the applicable plan/standard/ADR first;
2. change architecture tests with implementation;
3. migrate affected documentation/navigation in the same change;
4. update the [cross-domain dependency map](cross-domain-dependency-map.md) if ownership direction changes; and
5. refresh this audit against an exact protected-green revision.

Do not preserve obsolete duplicate directories, redirect/stub compatibility files, or stale living guides solely to maintain historical paths.
