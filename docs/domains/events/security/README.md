# Events security

[← Events domain](../README.md)

**Document type:** Security capability contract
**Status:** Current
**Owner:** Events with Authorization, Memberships, Kingdoms, Notifications, and Rallies
**Primary security boundary:** exact Event scope, exact target, and an authorized operation

## Security model

Events authorizes every protected operation against one explicit scope and target. Player, Alliance, and Kingdom permissions are separate permission families. A powerful role in one scope does not grant authority in another.

## Player scope

Player self-service uses authoritative `players.user_id` ownership. The active Player session context is revalidated against the authenticated User on every request, and self Player-scoped mutations require the target Player to equal that active Player. Player-management authority for another Player is evaluated separately through that Player's current Alliance and does not depend on the manager's active Player context.

## Alliance scope

Alliance Event access uses `AllianceAuthorization`. R4/R5 rank permissions and additive specialist roles may grant Event authority according to the configured permission bundle.

## Kingdom scope

Kingdom Event access uses exact-Kingdom role assignments. Alliance rank, Alliance specialist roles, and platform-administrator status do not independently grant Kingdom Event authority.

## Catalogue administration

Event Type configuration is a privileged platform operation protected by platform-administrator middleware and recent password confirmation. System slugs are immutable; scope defaults and capabilities are configurable.


## Authorship and persona separation

`created_by_user_id` and `updated_by_user_id` identify the authenticated application account and remain the authoritative audit identity. `created_by_player_id` and `updated_by_player_id` optionally record the validated active Player persona. Event target columns are independent of both authorship fields, so a coordinator may manage a Player/Alliance/Kingdom Event without being mistaken for its target.

Event forms do not accept an actor Player identifier.

## Data protection

Event instructions, participation, rosters, assignments, objectives, and results are private operational data unless a specific surface explicitly publishes them. Logs and traces should record identifiers and outcomes without copying private Event content.

Calendar discovery is SQL-scoped to targets the User can currently view. Player-specific attention and reminder inboxes apply an additional active-Player eligibility boundary: the selected Player must still be owned by the User and currently belong to the Event's Player, Alliance, or Kingdom context. Stale roster or Kingdom context therefore does not keep Player-specific operational prompts visible.

## Failure behavior

Authorization fails closed for scope/permission mismatch, scope/target mismatch, forged/stale active Player context, self-target mismatch, inactive Alliance membership, conflicting Player ownership, and cross-Kingdom role use.

## Related documents

- [Events](../README.md)
- [Authorization](../../authorization/README.md)
- [Kingdom-scoped roles](../../authorization/kingdom-scoped-roles.md)
