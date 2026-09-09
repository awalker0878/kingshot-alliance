# Alliance — Membership

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Membership`

Membership owns Player membership, invitations and R1–R5 leadership behavior for an Alliance.

## Invariants

- Alliance membership belongs to `player_id`, not User;
- authority is never aggregated across a User's Players;
- invitations and membership transitions target Player identity where the game relationship is Player-specific;
- linked-provider and web self-service actions revalidate active membership through this context; observational roster state is not authority for those writes;
- leadership changes revalidate current membership/scope authority inside the owning write path;
- Platform Administrator is not an Alliance bypass.

Membership administration lists use the Alliance Dashboard ReadModel and a scope-bound cursor; the owner context remains the only write path. Bulk status changes accept no more than 50 explicit membership IDs, preview current hierarchy, Kingdom, exclusivity and capacity rules, and then repeat authorization through the single-membership action at commit time. Each requested membership receives a stable success, failure or skip code, while aggregate and per-membership audit evidence remain distinct.

Specialist permission interpretation belongs to `Alliance/Access`.

Rank administration also requires current role-management permission. Its target rank cannot exceed the actor's current rank, because ranks carry implicit authority in other contexts. The Membership rank enum owns that ceiling; preview uses current membership and each commit rechecks locked membership. Self-rank changes remain prohibited and R5 changes require leadership transfer. See [ADR-0022](../../adr/0022-bounded-alliance-delegation.md).
