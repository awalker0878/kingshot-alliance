# Product terminology

Status: Current

This document defines the product-language contract for user-facing Kingshot Alliance experiences and distinguishes it from internal engineering terminology.

## Product-language principles

1. **Prefer official Kingshot nouns.** User-facing copy uses terms players already see in Kingshot, including **Governor**, **Alliance**, **Kingdom**, **Town Center**, **Hero**, **Rally**, **Formation**, **Event**, **Alliance Notice**, **Gift Code**, and **Kingdom Transfer**.
2. **Use plain product verbs.** Prefer `Sign in`, `Schedule Event`, `Save`, `Cancel`, `Retry`, `Review`, and `Manage` over invented ceremony or architecture language.
3. **Do not theme navigation at the expense of recognition.** Product navigation must describe the destination. Faux-fantasy labels such as `Realm Gate`, `Alliance Hall`, `Recruitment Hall`, and `Event Codex` are not product terms.
4. **Do not expose architecture vocabulary to ordinary users.** Terms such as `authority context`, `read model`, `projection`, `bounded retry`, `command surface`, and `context owner` belong in diagnostics or engineering documentation, not normal player/officer workflows.
5. **Keep administrative precision where it is useful.** Platform-administration and integration screens may use established technical nouns such as API credential, webhook, endpoint, delivery, retry, and correlation ID when those are the actual objects being managed.
6. **Keep code names stable unless they are genuinely misleading.** Internal class/context names are not renamed just to match display copy. A domain concept is renamed only when evidence shows that the concept itself is incorrectly named, not because a friendlier label exists.
7. **Translations preserve the same product concept.** Every supported locale should translate the meaning of the canonical product noun/action rather than translating retired fantasy wording literally. Official game names and event names remain proper nouns unless Kingshot publishes a localized name used by the product.

## Canonical user-facing nouns

| Product concept | User-facing English | Internal/engineering note |
| --- | --- | --- |
| Game persona | **Governor** | The code/domain principal remains `Player`. |
| Selected game persona | **Active Governor** | Internally `active Player` / Player context. |
| Account | **Account** / **Governor Account** where game identity is relevant | A User may own multiple Players/Governors. |
| Alliance | **Alliance** | Do not substitute `Alliance Hall`, `banner`, or another invented noun for navigation. |
| Alliance member list | **Alliance Roster** | `Membership` remains valid when discussing the relationship/lifecycle itself. |
| Alliance message | **Alliance Notice** / **Noticeboard** when the page contains broader Alliance content | Use the in-game noun when referring specifically to the game-style notice. |
| Kingdom movement | **Kingdom Transfer** | Preserve official transfer nouns such as Power Cap, Transfer Score, and Transfer Pass when implemented. |
| Game activity | **Event** | Use the actual Event name where known: Bear Hunt, Swordland Showdown, Kingdom of Power, etc. |
| Reusable Event setup | **Event template** | Do not call it an Event pattern. |
| Event feature area | **Event tools** | Internal capability/module terminology stays internal. |
| Rally planning | **Rally plan** / **Rally planning** | `RallyGuidance` may remain an internal model name. |
| Event participant grouping | **Event roster** | `Roster operations` is not user-facing copy. |
| Player result history | **Governor Event history** | Avoid `planning intelligence` for a normal personal history screen. |
| Platform administration | **Platform administration** | `Citadel` may remain an internal route/folder name; it is not required as a display label. |

## Canonical actions and navigation

Use these English labels as the translation source of truth:

- `Home`
- `Sign in`
- `Sign out`
- `Create account`
- `Alliance Overview`
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
- `Schedule Event`
- `Event templates`
- `Event tools`
- `Rally planning`
- `Rally plan`
- `Event roster`
- `Event results`
- `KingShot Event Types`

## Internal terms

### User

Authenticated application account. A User may own multiple Players. `User` is not the game-domain authority holder.

### Player

Durable Kingshot persona and game-domain principal. Game authority is evaluated for the active Player. **Player is an internal/domain term; normal user-facing copy calls this a Governor.**

### Active Player

The Player currently selected for the request/session. Switching active Player changes effective game-domain authority. **The UI label is Active Governor.**

### Alliance

Managed Alliance tenant/community. Alliance authority is carried by Player membership, rank and specialist roles.

### Membership

Player-to-Alliance relationship that carries membership lifecycle, R1–R5 rank and role assignments as defined by Alliance.

### Kingdom

Game-world Kingdom identity and governance scope.

### Event

Operationally scheduled game activity owned by the Operations context. An Event may have recurrence, occurrences, phases and enabled operational capabilities.

### Observation

Captured intelligence fact about the game world. It is not the same as the neutral GameWorld identity record.

### ReadModel

Read-only projection that combines data from multiple owners for a screen/report. This is an engineering term and should not appear in ordinary user-facing copy.

### Workflow

Explicit orchestration of one user intent across several context owners; it does not own their aggregates. This is an engineering term, not a navigation label.

### Platform Administrator

User-scoped cross-tenant application administrator. It is not Alliance/Kingdom/Event authority.

## Evidence and naming changes

Official Century Games material is the preferred source for game nouns. Community tooling may identify a possible terminology mismatch, but it is not enough by itself to rename a domain concept. For example, `KingPerks` remains unchanged until an authoritative Kingshot term for that exact capability is verified.
