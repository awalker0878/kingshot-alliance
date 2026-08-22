# FRONTEND-V3 — Kingshot Alliance Command

## Purpose

FRONTEND-V3 is a clean-room Inertia/Vue interface for the ARCH-V3 backend. The browser presents the application in Kingshot game language while the backend architecture remains invisible.

## Stack

- Inertia 3
- Vue 3 (`<script setup lang="ts">`)
- TypeScript (strict)
- Tailwind CSS 4
- Vite 8

## Player-facing page map

Routable Vue pages live only under these game-facing surfaces:

- `Command/`
- `Accounts/`
- `Alliance/`
- `Operations/`
- `Intelligence/`
- `Kingdom/`
- `Citadel/`
- `Public/`

Backend terms such as `ReadModels`, `GameWorld`, `Platform`, persistence layers, contexts, actions, and authority objects must not become frontend page taxonomy or visible copy.

Territory planning is presented as **Territory Command** under the Kingdom/Alliance operational experience. The browser never exposes `KingdomMaps` or `TerritoryPlanning` as architecture vocabulary.

## Boundary rules

1. Laravel owns authoritative state and authorization.
2. Inertia page props are presentation contracts, not Eloquent models used as a browser API.
3. The active game identity is presented as the **Governor**; account identity remains separate.
4. Frontend permission flags only control affordances. Every write is authorized again by the server.
5. Mutations send IDs, scalars, enums, and form values. The browser does not submit backend model-shaped authority objects.
6. Server state is not duplicated into a global domain store. Inertia navigation/partial reloads remain the default state model.
7. Cross-capability composite reads can be rendered on the game surface they serve without exposing the backend `ReadModels` concept.
8. Territory editor pointer movement is local working state. Save/publish submits a coherent proposed layout with the expected server revision rather than one mutation per drag.
9. Browser geometry is a preview contract. Laravel repeats authoritative placement/rule validation before persistence.

## Territory Command rendering/accessibility contract

The spatial editor may use Canvas 2D for efficient map rendering, but canvas is never the only interaction surface.

- Every planned object has a synchronized semantic DOM row/control with type, Alliance, label, exact X/Y and validation state.
- Placement failures are announced in text and cannot rely on red/green alone.
- Keyboard users can select objects, edit exact coordinates, nudge, rotate, group/ungroup and delete through normal controls.
- Map dataset/version/provenance and non-official march assumptions are visible where they affect interpretation.
- Static map/reference layers are lazy-loaded with the planner; ordinary pages do not inherit the planner dataset/rendering cost.

## Game-language rules

Visible text should describe what a Kingshot Governor or Alliance officer is doing. Examples:

- Command Overview
- Alliance Hall
- Recruitment Hall
- Event Command
- Alliance Muster
- Rally Command
- Battle Plan
- War Report
- King's Court
- Intel Room
- Kingdom Alliances
- Glory Ledger
- Kingdom Transfer
- Territory Command
- Hive Builder
- Layout Analysis
- Noticeboard
- Alliance Connections
- Citadel

Software architecture vocabulary is prohibited from ordinary player-facing copy. `tests/v3/Frontend/verify-copy.php` enforces this rule.

## Capability truth

The frontend may only expose functionality backed by the application. The authoritative presentation map is `docs/frontend/FRONTEND-V3-CAPABILITY-MAP.md`.

Notable examples:

- King's Court appears only on event occurrences that expose `king_perks`.
- Rally Command appears only when formations or rally guidance exist.
- Battle Plan appears only when the event exposes objectives.
- War Report appears only when result capability/data is present.
- Intel Room is Alliance-owned intelligence contributed by Governors.
- Territory Command appears only when a current Player has a permitted Alliance/Kingdom planning scope and uses real `GameWorld/KingdomMaps` + `Operations/TerritoryPlanning` contracts.
- No donation totals, global leaderboard, gift level, inventory or invented event system is introduced.

## Visual system

The UI is inspired by Kingshot's bright medieval strategy-fantasy language but is implemented as an original web design system:

- night stone and dark timber command surfaces
- kingdom teal and royal gold
- parchment ledgers for dense records
- heraldic crests, seals, event sigils, and Governor plaques
- cinematic room banners
- restrained game ornament around modern, readable data tables and forms

Runtime artwork under `public/images/kingshot/` is independently authored for this frontend. Full-screen concept images are reference material only and must never be imported into runtime Vue/CSS.

## Shared components

Game-specific presentation primitives live under `resources/js/components/game/` and remain presentation-only. Generic reusable controls remain under `components/ui/`.

Mutation surfaces use the shared feedback primitives:

- `AppButton` owns disabled and visible/announced busy behavior;
- `FormError` gives server validation an alert region that inputs reference with `aria-describedby` and `aria-invalid`;
- `ActionNotice` presents translated success, warning and failure outcomes without exposing internal status codes;
- `ConfirmActionDialog` provides a keyboard-contained, initially focused confirmation for irreversible or access-revoking actions.
- Browser-native confirmation prompts are prohibited.

New mutation forms must expose server validation beside the affected control, prevent duplicate submission while busy and translate completion status. Native `window.confirm` and raw server status values are not the standard for new work.

## Validation

Dependency-free gates:

```bash
php tests/v3/Frontend/verify-architecture.php
php tests/v3/Frontend/verify-copy.php
php tests/v3/Frontend/verify-source.php
```

With the declared Node/npm toolchain installed:

```bash
npm ci
npm run check
npm run build
```

The Playwright suite under `tests/v3/Visual` must include Territory Command desktop/mobile/reduced-motion coverage once the capability routes are enabled.
