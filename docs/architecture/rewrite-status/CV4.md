# Context Model V4 Convergence Status

## Objective

Complete the post-FRONTEND-V3 convergence onto one server-authoritative Governor context model without compatibility shims, legacy fallback contracts, aliases, dual APIs, or knowingly dead code.

## Program invariants

- The Platform User authenticates; the active Governor acts.
- Browser state is presentation state, never authority.
- Game-domain writes are re-authorized by the owning context and protected by the current authority-context version.
- Context-bound UI state cannot silently survive a Governor/Alliance/Kingdom authority change.
- Each phase is a hard cutover: migrate every consumer, delete the replaced contract, then pass gates.
- No phase is marked PASS with a known branch-specific blocker.

## Phase ledger

| Phase | Scope | Status |
|---|---|---|
| CV4-P0 | Restore authoritative green baseline and phase ledger | VALIDATING |
| CV4-P1 | Replace `playerContext` with canonical `gameContext` | VALIDATING |
| CV4-P2 | Remove per-page shell viewer/context duplication | VALIDATING |
| CV4-P3 | Thin frontend game-context API | VALIDATING |
| CV4-P4 | One route/navigation context registry | VALIDATING |
| CV4-P5 | Command Overview read model cutover | NOT STARTED |
| CV4-P6 | Context-aware form runtime | NOT STARTED |
| CV4-P7 | Event Command decomposition | NOT STARTED |
| CV4-P8 | Royal Court / oversized-surface decomposition | NOT STARTED |
| CV4-P9 | Remove rank-derived presentation authority | NOT STARTED |
| CV4-P10 | Immutable backend request-context consolidation | NOT STARTED |
| CV4-P11 | Context-aware notifications and deep links | NOT STARTED |
| CV4-P12 | Final isolation/accessibility/visual certification | NOT STARTED |

## Validation ledger

### CV4-P0

- Repository-wide Pint baseline repaired under the repository's existing Pint configuration.
- One-shot repair workflow removed itself after committing the formatted baseline.

### CV4-P1 through CV4-P4

Implemented as one hard cutover because the contracts are mutually dependent:

- `playerContext` / `SharedPlayerContext` replaced by canonical `gameContext` / `SharedGameContext`.
- Shared authenticated `viewer` added for shell identity; `AppLayout` no longer accepts User/Alliance context props.
- `useGameContext()` and focused read helpers are the frontend context API; no parallel domain store was introduced.
- Inactive Governors carry identity/display context only; effective Alliance/Kingdom capabilities live under the active context.
- Context fingerprints now include Alliance and Kingdom authority inputs.
- `player:` browser storage scope replaced by `governor:` scope.
- `PlayerSwitchRouteResolver` removed.
- One `GameRouteRegistry` now produces permitted navigation and post-switch safe-parent routing.
- `AppLayout` renders only server-permitted rooms; client-side `allianceScoped` / `requiredCapability` policy tables were removed.
- All page-level `AppLayout` compatibility props were removed.
- Temporary migration scripts/workflows removed in the same cutover.

Status remains VALIDATING until current PR workflows and architecture/source gates pass on the post-cutover tree.
