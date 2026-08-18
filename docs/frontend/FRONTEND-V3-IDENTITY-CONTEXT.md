# FRONTEND-V3 Platform User and Active Governor Context

## Status

Ready for implementation and regression enforcement as part of Frontend V3.

This document defines the frontend identity contract for the authenticated application shell.
It complements the Frontend V3 capability map and the backend authority model.

## Core invariant

```text
THE PLATFORM USER AUTHENTICATES.
THE ACTIVE GOVERNOR ACTS.
```

A Platform User is the account and authentication principal. A Governor/Player is the game-domain actor.
One Platform User may own multiple Governors. Selecting another Governor never changes the authenticated account; it changes the active game identity and therefore the Alliance, Kingdom, rank, specialist roles, capabilities, navigation, data and actions available in the application.

```text
Platform User
    |
    +-- Governor A
    |      +-- Alliance / Kingdom authority
    |
    +-- Governor B
    |      +-- different Alliance / Kingdom authority
    |
    +-- Governor C
           +-- different Alliance / Kingdom authority
```

## Authority hierarchy

The browser presents authority; it does not establish it.

```text
Authenticated Platform User
        |
        v
Server-resolved Active PlayerReference
        |
        v
Alliance / Kingdom context
        |
        v
Effective capabilities
        |
        v
Frontend presentation
```

The activation request contains only the Player reference. The client must not submit `alliance_id`, `kingdom_id`, membership, rank, role or capability values as authority.

The backend validates that the requested Player belongs to the authenticated Platform User before storing the active Player selection.

## Shared shell contract

The global Inertia `playerContext` is the authoritative presentation snapshot for the application shell.

Conceptually:

```text
playerContext
+-- activePlayerId
+-- players[]
    +-- id
    +-- name
    +-- gamePlayerId
    +-- kingdomNumber
    +-- alliance?
        +-- id
        +-- name
        +-- rank
        +-- roles[]
        +-- capabilities[]
```

The Alliance/rank/role/capability information in this payload is a read projection for presentation and navigation. Backend authorization remains mandatory for every protected read and write.

Frontend code should consume the shared types in `resources/js/types/player-context.ts` rather than redefining Player identity shapes per page or component.

## Persistent identity visibility

The authenticated shell must make the active Governor visually obvious on desktop, tablet and mobile.

The identity surface should provide, when available:

- Governor name
- Kingdom number
- Alliance name
- R1-R5 rank
- specialist Alliance roles

A single-Governor account still displays its active identity but does not need a switch affordance. A multi-Governor account exposes the switcher in the persistent shell.

## Switch lifecycle

The current Phase 1 switch behavior intentionally uses a conservative safe destination:

```text
Governor switch requested
        |
        v
POST /players/{player}/activate
        |
        v
Server validates ownership
        |
        v
Session active Player changes
        |
        v
Redirect to Command Overview
        |
        v
Fresh Inertia page + shared context
```

The frontend sets `preserveState: false` and `preserveScroll: false`. This deliberately prevents component-local state from the previous Governor from surviving the identity transition.

Returning to Command Overview is the safe route-reconciliation strategy for this slice. A later enhancement may preserve the logical route only after a server-aware route-capability resolver can prove that the equivalent destination is valid for the new Governor.

## Cross-identity isolation

After a successful switch, UI state rendered for the previous Governor must not remain usable or visible as authoritative context.

This includes:

- Alliance membership and rank
- specialist roles
- Kingdom context
- permission-shaped navigation
- roster and intelligence views
- event context
- administrative forms and mutations

The current Inertia transition performs a full state replacement. Any future client-side query/cache layer must additionally include active Player context in cache keys or explicitly invalidate Player-, Alliance- and Kingdom-scoped entries during a switch.

A context-bound form must never be submitted under a different active Governor than the one under which it was created.

## Capability-shaped navigation

Navigation visibility is usability, not authorization.

The shell should use server-projected effective capabilities for destinations that are permission-gated by their owning backend context. For example, Recruitment Hall is available only when the active Governor has `recruitment.manage`.

The frontend must not infer effective permission from rank with logic such as `rank === 'r5'`. Rank is presentation data. Effective capabilities shape the UI; backend authorization independently enforces access.

Membership-scoped rooms remain enabled when the active Governor has an Alliance unless the owning backend capability defines a stronger permission requirement.

## Accessibility

The switcher must support:

- keyboard focus
- Enter/click activation
- Arrow Up / Arrow Down option movement
- Home / End movement
- Escape to close
- `aria-expanded` and listbox/option semantics
- visible focus treatment
- an `aria-live` status while identity switching is in progress
- equivalent capability on mobile and desktop

The active identity must never rely on color alone.

## Failure behavior

If activation fails, the backend must not establish the requested Player context. The current Governor remains the valid context.

The switcher prevents concurrent activation requests while a switch is pending. Unexpected errors should continue through the platform's normal user-safe error handling and correlation-ID strategy.

If a previously stored Player is no longer owned by or available to the authenticated user, `ResolvePlayerContext` rejects that stale selection rather than trusting the browser session value.

## Regression requirements

The Frontend V3 test suite must guard these invariants:

- one shared `SharedPlayerContext` frontend contract
- switch request contains only the Player reference
- state is not preserved across a switch
- all available Governors may carry Alliance, rank and specialist-role labels
- capability-shaped navigation uses server-projected capabilities
- keyboard and screen-reader affordances remain present
- activation remains ownership-validated on the server
- post-switch routing returns to a safe page

## Future route reconciliation

The eventual richer route strategy should be:

```text
Current logical route valid for new Governor?
        |
        +-- yes -> reload equivalent route in new context
        |
        +-- no  -> nearest permitted parent
                    |
                    +-- unavailable -> Command Overview
```

That change must not be implemented as a client-only permission decision. The server remains authoritative for whether the new Governor may access the destination.

## Decision summary

Approved:

- Platform User and Governor are separate identities.
- A Platform User can own and switch among multiple Governors.
- Active Governor is persistent and visible in the shell.
- Switching requests only a Player reference.
- Server ownership validation establishes the new context.
- Alliance/rank/role/capability data is projected for presentation.
- Effective capabilities shape permission-gated navigation.
- Inertia state is replaced on identity transition.
- Command Overview is the safe Phase 1 post-switch destination.
- Accessibility and cross-identity isolation are regression requirements.

Rejected:

- treating the authenticated Platform User as the game actor
- using Alliance as the primary identity
- client-supplied Alliance, Kingdom, rank, role or capability authority
- merging capabilities from different Governors
- reusing stale page state after a switch
- inferring backend authority from R-rank alone
- desktop-only identity switching
