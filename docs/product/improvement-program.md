# Product and engineering improvement program

Status: In progress

This program begins after the capability-completeness delivery recorded in the [delivery ledger](capability-delivery-ledger.md). It improves workflow depth, integration coverage, supportability and user experience without reopening completed architecture migrations or inventing unsupported game data.

## Delivery order

| Phase | Status | Outcome |
| --- | --- | --- |
| 0. Baseline and cleanup | Complete | Current inventory, enforceable documentation integrity and removal of obsolete migration evidence from the live documentation tree. |
| 1. Architecture enforcement | Planned | Simpler owner-context APIs, immutable request authority and stronger automated boundary checks. |
| 2. UX system | Planned | Predictable navigation, actions, forms, validation, empty states, recovery and mobile behavior. |
| 3. Workflow completion | Planned | Consistent create/validate/authorize/commit/notify/audit/recover behavior across core capabilities. |
| 4. Integration platform | Planned | Broader documented webhook contracts, delivery testing/replay and API contract coverage. |
| 5. Operations and security | Planned | Actionable diagnostics, bounded recovery, security controls and performance budgets. |
| 6. Accessibility and localization | Planned | Keyboard, screen-reader, responsive and localization quality verification across primary journeys. |
| 7. Calculators | Evidence-gated | Source-backed, versioned datasets and one calculator delivered end to end only after the existing evidence gate passes. |
| 8. Closeout | Planned | Full CI, staging, backup/restore, documentation and fresh-install reconciliation. |

## Slice rule

Each pull request delivers one complete vertical slice. Code, authorization, user feedback, failure recovery, tests, observability and applicable documentation change together. A later phase does not compensate for an incomplete current slice.

Breaking cleanup is preferred while the application is not deployed. Removed contracts are removed in the same change as their callers; no aliases, dual reads, dual writes, compatibility routes or static-analysis baselines are introduced.

## Documentation rule

- Ownership or dependency changes update `docs/architecture` and, when the decision is material, its authoritative architecture explanation.
- Implementation patterns update `docs/codebase`.
- User outcomes and journeys update `docs/product`.
- API and webhook contracts update `docs/reference`.
- Runtime, deployment and recovery changes update `docs/operations`.
- Obsolete program evidence is deleted from live documentation; Git history remains the archive.

The documentation-link gate runs as part of `npm run check` so moved or removed current documentation cannot leave broken internal references.

## Phase 0 evidence

- The canonical capability inventory remains the [capability map](../architecture/capability-map.md); the [capability-completeness plan](capability-gap-analysis.md) records user-facing coverage.
- Completed clean-room rewrite phase reports were removed from live documentation. Their commits remain available in Git history.
- Misleading V4/legacy surface commentary was removed from the current stylesheet.
- Internal documentation links are now validated in the frontend CI gate.
- Frontend lint, formatting, type checking, localization coverage, production build and localization chunk checks passed on the phase head.

## Gate before merge

Every applicable PHP, frontend, architecture, security, visual, container, staging and recovery check must pass. A phase is complete only when its final merged head and the authoritative documents agree.
