# FRONTEND-V3 Rewrite Status

## Scope

Clean-room frontend rebuild on the certified ARCH-V3 backend using the Kingshot Alliance Command visual language and only implemented application capabilities.

Visual/Playwright reconstruction is intentionally excluded from this program and remains separate work.

## Phase ledger

| Phase | Scope | Status |
|---|---|---|
| FE-P0 | Architecture, game-language and capability rules | PASS |
| FE-P1 | Inertia 3 / Vue 3 / Tailwind 4 / Vite 8 configuration | PASS (configuration) |
| FE-P2 | Strict TypeScript foundation | PASS (configuration/source) |
| FE-P3 | Kingshot visual foundations and independently authored runtime art | PASS |
| FE-P4 | Application/Auth/Public command shells | PASS |
| FE-P5 | Game-facing routable page taxonomy | PASS |
| FE-P6 | Governor account and public realm surfaces | PASS |
| FE-P7 | Alliance Hall, members, recruitment, noticeboard and connections | PASS |
| FE-P8 | Event Command and capability-aware event rooms | PASS |
| FE-P9 | King's Court / King Perks presentation | PASS |
| FE-P10 | Intel Room roster, Kingdom Alliance intelligence, sharing and Glory Ledger | PASS |
| FE-P11 | Kingdom roles/settings and Kingdom Transfer | PASS |
| FE-P12 | Citadel stewardship surfaces | PASS |
| FE-P13 | Generic communications presentation retained without event-specific ownership leakage | PASS |
| FE-P14 | Inertia/server-state patterns retained; no parallel global domain store introduced | PASS |
| FE-P15 | Existing mutation contracts preserved behind new presentation paths | PASS |
| FE-P16 | Existing validation/status/error surfaces retained in game presentation | PASS |
| FE-P17 | Shared Kingshot component system | PASS |
| FE-P18 | Tailwind 4 theme and legacy SaaS palette removal | PASS |
| FE-P19 | Frontend architecture/copy/source enforcement | PASS |
| FE-P20 | Non-visual source certification and package validation | PASS |

## Current validation

- FRONTEND-V3 architecture gate: PASS
- FRONTEND-V3 game-language/copy gate: PASS
- FRONTEND-V3 source/import/art gate: PASS
- Backend ARCH-V3 compatibility: PASS
- PHP syntax for changed PHP adapters: PASS (44 changed application PHP adapters; frontend verifier PHP files also execute successfully)
- `git diff --check`: PASS
- fresh patch apply: PASS against backend baseline `a66b8351d105702058fbf4c3b9dfc674cc618d9f`

## Environment limitation

The repository declares Node 24 and npm 11 for the Vite 8 frontend. The execution container provides Node 22.16.0 and npm 10.9.2. An `npm ci` attempt reported `EBADENGINE` for the declared toolchain and did not complete, so the partial `node_modules` directory was removed. Therefore a production Vite build, `vue-tsc`, ESLint and Prettier are not certified by this environment. They must be run with Node 24/npm 11. This limitation is not reported as a passing build.

## Deferred

- Playwright / visual regression tests: separate rewrite requested by the user.
- GitHub workflow migration related to the previous V2 visual suite: outside this frontend package.
