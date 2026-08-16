# Membership and authority

Status: Current  
Context: Alliance  
Implementation: `app/Contexts/Alliance/Membership` and `app/Contexts/Alliance/Access`

## Identity model

Alliance membership belongs to a Player through `player_id`, not to User. R1–R5 rank and specialist roles are attributes/assignments of the Player's Alliance membership.

## Authority

Effective Alliance permissions are evaluated from the active Player's current membership, rank and specialist roles in the concrete Alliance scope. Current permission vocabulary includes:

- `alliance.view`
- `alliance.manage`
- `membership.manage`
- `roles.manage`
- `invitations.manage`
- `content.manage`
- `recruitment.manage`

## Invariants

- no authority aggregation across a User's Players;
- switching active Player changes Alliance authority;
- invitations/membership transitions target Player identity where the game relationship is Player-specific;
- leadership/role changes use current transaction-time authorization rather than stale request-time assumptions;
- Platform Administrator has no Alliance authorization bypass.

## Consumers

Operations and Intelligence may consume current Alliance membership/governance facts but define their own `events.*`/Intelligence permission semantics.