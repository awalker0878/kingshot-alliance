# Product terminology

Status: Current

This document is the product-language contract for user-facing Kingshot Alliance experiences. It defines the canonical English concepts that translations must preserve and separates product language from implementation vocabulary.

## Source priority

Use naming evidence in this order:

1. **Official Kingshot game and Century Games material.** This is authoritative for game nouns and named systems.
2. **Kingshot Help Center.** Use support terminology when the game UI noun is documented there.
3. **Plain product language.** When Kingshot has no specific noun for an application workflow, use a direct descriptive label such as `Home`, `Manage`, `History`, or `Scout findings`.
4. **Community terminology only as a discovery signal.** Community tools may reveal a mismatch, but they do not establish a canonical game noun.

Current authoritative examples:

- Century Games, [Kingdom Transfer](https://www.centurygames.com/kingshot-kingdom-transfer/): **Governor**, **Kingdom Transfer**, **Transfer Manager**, **Power Cap**, **Transfer Score**, **Transfer Pass**, **Leading Kingdom**, **Ordinary Kingdom**.
- Century Games, [December Update](https://www.centurygames.com/kingshot-december-update/): **Alliance Notice**, **Alliance Rules**, **Formation**, **Rally**, **Governor**.
- Kingshot Help Center, [Position Perks and King's Skills](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/9062-do-position-perks-and-king-s-skills-take-effect-1783425608/): **Position Perks** and **King's Skills**.

If official terminology changes, update this document, English source copy, translations, and language checks in the same change.

## Product-language principles

1. **Prefer official Kingshot nouns.** Use the words Governors already see in Kingshot when referring to a game concept.
2. **Use plain product verbs.** Prefer `Sign in`, `Schedule Event`, `Save`, `Cancel`, `Retry`, `Review`, `Manage`, `Confirm`, and `Dismiss` over invented ceremony.
3. **Navigation describes destinations.** Do not theme navigation with faux-fantasy labels such as `Realm Gate`, `Alliance Hall`, `Recruitment Hall`, `Royal Court`, `Citadel`, `Glory Ledger`, or `Event Codex`.
4. **Do not expose architecture vocabulary to ordinary users.** `authority context`, `read model`, `projection`, `bounded retry`, `persistence`, `append-only`, `idempotent`, `quarantine`, `staged`, and similar implementation terms belong in code, diagnostics, or engineering documentation when technically necessary.
5. **Keep technical precision on technical screens.** Platform Administration and Connections may use actual operator/API objects such as API credential, webhook, endpoint, outbox, queue partition, correlation ID, signing key, retry, and idempotency key.
6. **Do not rename domain classes cosmetically.** Internal names may differ from display copy. Rename a domain or ownership concept only when it is actually misleading about responsibility or behavior.
7. **Rename misleading presentation concepts immediately.** Page namespaces, route-facing component names, and read-surface names should not preserve retired product metaphors just because localization hides them.
8. **Translations preserve concepts, not literal retired wording.** Every supported locale translates the canonical product meaning. Do not translate old fantasy labels literally.
9. **Receipts, confirmations, empty states, and errors use product language too.** A successful localized title does not excuse technical or themed wording in supporting states.

## Canonical user-facing nouns

| Product concept | User-facing English | Internal/engineering note |
| --- | --- | --- |
| Game persona | **Governor** | The code/domain principal remains `Player`. |
| Selected game persona | **Active Governor** | Internally active Player / Player context. |
| Application account | **Account** / **Governor Account** when game identity is relevant | A User may own multiple Players/Governors. |
| Alliance | **Alliance** | Do not substitute Hall, banner, fleet, command, or another invented noun. |
| Alliance member list | **Alliance Roster** | `Membership` remains valid for the relationship/lifecycle itself. |
| Alliance message | **Alliance Notice** | **Noticeboard** is the broader application area containing notices and other Alliance content. |
| Alliance guidance | **Alliance Rules** / **Guide** as appropriate | Use the official `Alliance Rules` noun when the content represents that in-game concept. |
| Kingdom movement | **Kingdom Transfer** | Preserve **Transfer Manager**, **Power Cap**, **Transfer Score**, and **Transfer Pass** where implemented. |
| Kingdom position benefit | **Position Perk** | The backend `KingPerks` context remains an internal implementation name. |
| Kingdom skill | **King's Skill** | Also implemented inside `KingPerks`; do not display `King Perk skill`. |
| Position scheduling area | **Kingdom Positions** / **Position Perks** | `Kingdom Positions` is a plain application grouping label, not a claim that Kingshot names a menu this way. |
| Game activity | **Event** | Prefer the exact official Event name where known. |
| Reusable Event setup | **Event template** | Do not call it an Event pattern. |
| Event feature area | **Event tools** | Capability/module terminology stays internal. |
| Rally coordination | **Rally plan** / **Rally planning** | `RallyGuidance` may remain an internal implementation name. |
| Governor troop setup | **Formation** | Official Kingshot noun. |
| Event participant grouping | **Event roster** | Do not display `Roster operations`. |
| Personal Event history | **Governor Event history** | Avoid `planning intelligence` for a normal personal history screen. |
| Kingdom data collection | **Scout Reports**, **scout records**, **scout findings**, **scout sources** | Do not expose ingestion/subscription/candidate/quarantine/replay pipeline vocabulary. |
| Platform administration | **Platform administration** / **Platform Administrator** | `Citadel` is retired from user-facing and presentation namespaces. |
| Signed-in landing page | **Home** / **Governor overview** | `/dashboard` is not the Alliance overview. |
| Alliance landing page | **Alliance** / **Alliance overview** | `/alliance` owns the Alliance-specific overview. |

## Canonical navigation and common actions

Use these English labels as translation source-of-truth concepts:

- `Home`
- `Alliance`
- `Events`
- `Alliance Roster`
- `Recruitment`
- `Noticeboard`
- `Alliance Contributions`
- `Kingdom Alliances`
- `Kingdom Transfer`
- `Connections`
- `Gift Codes`
- `Notifications`
- `Governor Account`
- `Settings`
- `Sign in`
- `Sign out`
- `Create account`
- `Schedule Event`
- `Event templates`
- `Event tools`
- `Rally planning`
- `Rally plan`
- `Event roster`
- `Event results`
- `Position Perks`
- `Kingdom Positions`
- `King's Skills`
- `Scout Reports`

## Internal-to-product mappings

### User

Authenticated application account. `User` is not the game-domain authority holder.

### Player

Durable Kingshot persona and game-domain principal. **Normal user-facing copy calls this a Governor.** `Player ID` remains valid when referring to the actual in-game identifier.

### Active Player

The Player selected for the request/session. Switching active Player changes effective game-domain authority. **The UI label is Active Governor.**

### Membership

Player-to-Alliance relationship carrying membership lifecycle, R1-R5 rank, and Alliance role assignments. `Membership` is appropriate in engineering and precise administrative contexts; normal member lists use `Alliance Roster` or `Alliance members`.

### KingPerks

Internal Operations capability covering Kingdom position appointments, Position Perk requests, and King's Skills. The class/context name is retained because changing it would be cosmetic domain churn. **Product copy must use Position Perks, Kingdom positions/appointments, and King's Skills according to the specific object being shown.**

### CommandOverview

Internal read model composing cross-context Home information. The engineering name may remain. The Inertia page is `Dashboard/Home`, and product copy calls the destination `Home` or `Governor overview`, not `Command` or `Alliance Overview`.

### Observation

Captured intelligence fact about the game world. Normal product copy should prefer `scout record` or `scout finding` according to state.

### ReadModel / Workflow

Engineering concepts. They may appear in architecture documentation and diagnostics where useful, but never as ordinary navigation, headings, empty states, confirmations, or receipts.

### Platform Administrator

User-scoped cross-Alliance application administrator. This is an application role, not an invented Kingshot title and not Alliance/Kingdom/Event authority.

## Presentation namespace rules

Presentation paths should express what the screen actually is. The following retired paths must not be reintroduced:

- `Command/Overview` → `Dashboard/Home`
- `Alliance/Hall` → `Alliance/Overview`
- `Alliance/Recruitment/Hall` → `Alliance/Recruitment/Index`
- `Intelligence/GloryLedger/*` → `Intelligence/Contributions/*`
- `Citadel/RealmControl` → `Platform/Administration`
- `Citadel/EventCodex` → `Platform/EventTypes`
- `Kingdom/RoyalCourt/*` → `Kingdom/PositionPerks/*`
- `Operations/Events/Chronicle` → `Operations/Events/History`

Do not add aliases, duplicate pages, dual render paths, or compatibility shims for these names.

## Review checklist

A product-language change is incomplete until all applicable items are checked:

- English localization values, including page titles, labels, empty states, help text, confirmations, errors, and result summaries.
- Typed action receipts.
- Raw Vue/layout/component copy that is not supplied by localization.
- Inertia page names and presentation namespaces when the old concept is genuinely misleading.
- All supported translations for changed product concepts.
- `/docs/product/terminology.md` when a canonical noun or mapping changes.
- `npm run check:product-language` plus the existing localization and receipt coverage checks.

## Automated enforcement

`scripts/check-product-language.mjs` enforces the semantic contract. It scans English catalogues, Vue pages/layouts/components, static offline copy, and presentation paths for retired product language and architecture terms that should not be user-facing. Platform Administration and Connections have a narrow technical-language exception because they expose real operator/API objects.

The existing page-localization and action-receipt coverage checks remain responsible for structural completeness. The product-language check is intentionally complementary: coverage proves that a string has a localization/receipt path; this contract checks whether the language itself is appropriate.
