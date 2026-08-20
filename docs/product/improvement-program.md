# Product and engineering improvement program

Status: In progress

This program begins after the capability-completeness delivery recorded in the [delivery ledger](capability-delivery-ledger.md). It improves workflow depth, integration coverage, supportability and user experience without reopening completed architecture migrations or inventing unsupported game data.

## Delivery order

| Phase | Status | Outcome |
| --- | --- | --- |
| 0. Baseline and cleanup | Complete | Current inventory, enforceable documentation integrity and removal of obsolete migration evidence from the live documentation tree. |
| 1. Architecture enforcement | Complete | Removed the remaining V2 visual-test structure and strengthened verification against compatibility-era test trees. |
| 2. UX system | Complete | Shared accessible busy, validation, status and confirmation patterns established on the high-risk connections surface. |
| 3. Workflow completion | Complete | Consistent create/validate/authorize/commit/notify/audit/recover behavior across core capabilities. |
| 4. Integration platform | Complete | Broader documented webhook contracts, targeted delivery testing, audited recovery and API contract coverage. |
| 5. Operations and security | Complete | Actionable diagnostics, bounded recovery, security controls and enforceable performance budgets. |
| 6. Accessibility and localization | Complete | Keyboard-contained, screen-reader-labelled and localized confirmation behavior enforced across destructive primary journeys. |
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

## Phase 1 evidence

- The active Playwright contract and its baselines now live under `tests/v3/Visual`; no `tests/v2` tree remains.
- The architecture verifier no longer preserves a deferred compatibility-era visual suite. It requires the current application-shell contract instead.
- Behavior-contract verification rejects every file type under a reintroduced `tests/v2` tree, not only PHP files.
- Frontend architecture documentation now describes the visual suite as a current quality gate.

## Phase 2 evidence

- Shared buttons now expose a visible busy state, disable duplicate submission and announce progress semantics.
- Shared validation and action-notice components give server errors and outcomes consistent live-region behavior.
- A reusable modal confirmation owns keyboard focus and blocks cancellation while a destructive request is running.
- Alliance Connections now translates action outcomes, relates validation errors to controls and confirms credential/Event-dispatch revocation.
- The frontend architecture document defines these primitives as the standard for new and touched mutation surfaces.

## Phase 3 and 4 evidence

- The first workflow/integration slice replaces two dead public webhook selectors with Alliance-scoped transitions that the application actually emits.
- Subscription validation, wildcard canonicalization, fan-out eligibility, management choices and contract documentation now share one catalogue.
- The guided event picker removes free-text selector errors and explains the user outcome of every supported transition.
- Behavior coverage proves wildcard delivery fan-out, idempotency and rejection of internal or removed event names.
- Managers can now send one targeted signed test through the production delivery path and manually re-queue an exhausted delivery without cloning or mutating its payload identity.
- Test and recovery actions are Alliance-scoped, password-confirmed, rate-limited, audited and covered by behavior tests.

## Phase 3 evidence

- The residual Gift Code workflow now opens the official provider handoff, exposes continuation and per-Governor timing, and uses shared accessible mutation feedback.
- Successful redemption is terminal and retryable provider outcomes enforce their persisted backoff under row lock, so repeated clicks cannot corrupt state or bypass recovery policy.
- Behavior tests cover terminal success and retry timing; the Gift Code reference documents the same lifecycle.

## Phase 5 evidence

- CI now fails when the initial JavaScript graph, application entry, largest lazy page or largest stylesheet exceeds its reviewed raw-byte ceiling.
- Operations documentation owns the thresholds and requires measurement plus review before a budget increase.
- The existing launch-health command covers configuration, administrator redundancy/MFA, Alliance defaults, outbox lag, failed jobs and webhook exhaustion; integration recovery is bounded, audited and exercised through staging plus backup/restore CI.
- Dependency review, CodeQL, locked dependency audits, security headers, production image scanning and endpoint policy remain mandatory gates rather than parallel application subsystems.

## Phase 6 evidence

- Browser-native confirmation prompts were removed from account deletion, Alliance membership, recruitment, roster, Event, Kingdom intelligence, Royal Court and transfer workflows.
- Every affected action now uses the shared modal contract with an explicit title, description, localized cancel/confirm labels, visible busy state, duplicate-submit protection and focus containment.
- The standard frontend check parses every Vue single-file component and rejects browser confirmation APIs or incomplete shared confirmation-dialog contracts.
- Existing focus visibility, reduced-motion behavior, responsive-table treatment, locale direction and localization coverage remain part of the same frontend and visual gates.

## Gate before merge

Every applicable PHP, frontend, architecture, security, visual, container, staging and recovery check must pass. A phase is complete only when its final merged head and the authoritative documents agree.
