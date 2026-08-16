# Alliance Membership

## Purpose

Owns Player membership in a platform Alliance, the authoritative KingShot Alliance rank (`R1`–`R5`), invitation lifecycle, and membership state transitions.

## Player-scoped authority invariant

Alliance membership and Alliance authority belong to a **Player**, not to a User.

- `AllianceMembership.player_id` identifies the game-domain principal that holds the membership.
- A User may own multiple Players, but no Alliance membership, rank, role, or permission is attached to `user_id`.
- The active Player is the principal used to establish Alliance access.
- Alliance access requires that active Player to have a current active membership in the concrete Alliance scope.
- R1-R5 rank belongs to the Player's membership.
- Specialist roles are additive assignments on the Player's membership.
- Alliance permissions are derived from that Player membership's rank and specialist roles.
- Permissions from another Player owned by the same User are never inherited, merged, or aggregated.
- Switching active Player therefore switches the effective Alliance membership and Alliance authority.

Account authentication may establish the operating User and support delivery concerns such as invitation email, but account identity does not grant Alliance authority.

## Core rank invariants

- Every active Alliance membership has exactly one rank.
- A newly accepted member starts at R1.
- Exactly one active membership per Alliance is R5.
- R5 is the Alliance owner/leader.
- R4 is officer rank.
- Specialist RBAC roles are additive and do not alter the R1-R5 rank.
- R5 cannot leave, be suspended, removed, or demoted through ordinary membership administration; leadership must be transferred.
- Leadership transfer promotes the target Player's membership to R5 and demotes the previous R5 membership to R4 atomically.

## Public contracts

- resolve active Player membership for Alliance-scoped access;
- `AllianceRank` and rank lifecycle;
- Player-targeted invitation create/revoke/resend/acceptance;
- `UpdateAllianceRank` for R1-R4 administration;
- leadership transfer between Player memberships; and
- dedicated self-service leave workflow.

## Dependencies

- `GameWorld` for Player identity and current Kingdom placement;
- `Alliance/Core` for the concrete Alliance scope;
- `Alliance/Access` for rank/role permission evaluation and transaction-time authorization; and
- Shared audit/messaging infrastructure for attributable, durable evidence.

The Membership capability must not depend on User-scoped authorization or a global business-permission domain.
