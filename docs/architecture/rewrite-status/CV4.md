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
| CV4-P0 | Restore authoritative green baseline and phase ledger | IN PROGRESS |
| CV4-P1 | Replace `playerContext` with canonical `gameContext` | NOT STARTED |
| CV4-P2 | Remove per-page shell viewer/context duplication | NOT STARTED |
| CV4-P3 | Thin frontend game-context API | NOT STARTED |
| CV4-P4 | One route/navigation context registry | NOT STARTED |
| CV4-P5 | Command Overview read model cutover | NOT STARTED |
| CV4-P6 | Context-aware form runtime | NOT STARTED |
| CV4-P7 | Event Command decomposition | NOT STARTED |
| CV4-P8 | Royal Court / oversized-surface decomposition | NOT STARTED |
| CV4-P9 | Remove rank-derived presentation authority | NOT STARTED |
| CV4-P10 | Immutable backend request-context consolidation | NOT STARTED |
| CV4-P11 | Context-aware notifications and deep links | NOT STARTED |
| CV4-P12 | Final isolation/accessibility/visual certification | NOT STARTED |

## Validation ledger

This section is updated with concrete command/workflow evidence as phases complete.
