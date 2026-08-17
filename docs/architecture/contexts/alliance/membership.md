# Alliance — Membership

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Membership`

Membership owns Player membership, invitations and R1–R5 leadership behavior for an Alliance.

## Invariants

- Alliance membership belongs to `player_id`, not User;
- authority is never aggregated across a User's Players;
- invitations and membership transitions target Player identity where the game relationship is Player-specific;
- leadership changes revalidate current membership/scope authority inside the owning write path;
- Platform Administrator is not an Alliance bypass.

Specialist permission interpretation belongs to `Alliance/Access`.