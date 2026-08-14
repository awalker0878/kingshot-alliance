# Player identity and active context

## Purpose

`Player` is the durable KingShot game identity. `User` is the authenticated application account. One User may own zero, one, or many Players; one Player belongs to at most one User.

`players.user_id` is the authoritative ownership relationship. Alliance roster membership and Kingdom placement describe current game context and never grant ownership by themselves.

## Ownership invariants

1. `players.user_id = users.id` is the only ownership fact used to list or activate a User's Players.
2. A roster manager may claim an unowned Player for the User behind a linked active Alliance membership, but may not relink a Player already owned by another User.
3. Leaving or changing an Alliance does not remove Player ownership.
4. Kingdom transfer changes `players.current_kingdom_id`; it does not create a new Player identity.
5. The same User may link multiple Players to the same Alliance membership.

## Active Player context

The active Player is an authenticated session context stored under `identity.active_player_id`.

Activation is performed through `POST /players/{player}/activate`. The route resolves the submitted ULID with both the Player primary key and `user_id = authenticated_user.id`; another User's Player therefore returns not found.

`ResolvePlayerContext` revalidates the session value on every authenticated web request. A stale, deleted, reassigned, or forged Player ID is removed from session rather than trusted.

The active Player is presented in the application shell as a switchable list sourced only from `players.user_id`. When a User owns exactly one Player it is activated automatically. When a User owns multiple Players, no identity is guessed: the User must choose one explicitly.

## Authorization boundary

Active Player context is **not** an RBAC grant.

- Player self-service authorization uses Player ownership plus the active Player context.
- Alliance authority comes from the authenticated User's active Alliance membership, R1-R5 rank, and specialist roles.
- Kingdom authority comes from an explicit role assignment for the exact Kingdom.
- Switching Player context cannot add Alliance or Kingdom permissions.

For a self Player-scoped mutation, the Event target must equal the active Player. A User who owns multiple Players must switch context before mutating another owned Player's self-scoped Event.

## Audit identity

Privileged and Event mutations keep account and game persona separate:

- `created_by_user_id` / `updated_by_user_id` identify the authenticated application account and are authoritative for accountability.
- `created_by_player_id` / `updated_by_player_id` record the validated active Player persona. Event mutation requires an active Player whenever the User owns one or more Players.
- Event `player_id`, `alliance_id`, or `kingdom_id` identify the Event target and are independent of authorship.

A User who owns no Player may still perform permitted Event administration with User-only authorship; Player authorship is then null. A User who owns Players must resolve an active Player before an Event mutation so game-persona authorship is never ambiguous.

## Threat controls

- **IDOR:** activation queries are constrained by `players.user_id`.
- **Session tampering/staleness:** active Player is re-resolved under authenticated ownership every request.
- **Privilege escalation:** Player context never feeds Alliance or Kingdom permission evaluation.
- **Persona spoofing:** Event forms do not accept an actor Player ID; actions consume only validated server-side context.
- **Ownership collision:** roster linking rejects a Player whose `user_id` belongs to another User.
- **Enumeration:** attempting to activate another User's Player returns the same not-found behavior as an unknown Player.
- **Audit ambiguity:** User account and selected Player persona are persisted separately.
