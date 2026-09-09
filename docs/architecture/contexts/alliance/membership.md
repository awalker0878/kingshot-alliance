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

Invitation onboarding acquires current account, existing Alliance and active Kingdom scope before claiming Player. Acceptance reloads current account email, owned canonical Player, invitation, roster and membership facts in the same transaction. Administrative activation holds its exclusive Alliance scope and a shared current Player lock; competing active membership is checked without locking another Alliance's rows and resolved through the unique constraint with savepoint recovery. Roster mutations acquire Player before the scoped entry and revalidate discovered routing. See [ADR-0031](../../adr/0031-current-membership-admission-and-roster-lock-order.md).

Initial invitation issuance, recruitment conversion and resend share InvitationEligibility. Under the existing exclusive Alliance scope, it stabilizes current canonical Player identity, validates active roster/Kingdom and recipient ownership, and checks active membership without locking a foreign Alliance's records. Resend preserves its existing reservation when still live and reacquires plan capacity when expired. Initial issuance atomically supersedes prior pending invitations for the same Player or recipient; token replacement, audit and outbox roll back together.

Rank administration also requires current role-management permission. Its target rank cannot exceed the actor's current rank, because ranks carry implicit authority in other contexts. The Membership rank enum owns that ceiling; preview uses current membership and each commit rechecks locked membership. Self-rank changes remain prohibited and R5 changes require leadership transfer. See [ADR-0022](../../adr/0022-bounded-alliance-delegation.md).

Account deletion holds the account owner barrier while RemovePlayersFromAlliances discovers all historical membership scopes. Cleanup acquires Alliance shared locks in ID order before current active membership locks, including inactive scopes whose members could be reactivated. It rejects newly appeared scopes before writes, rechecks current R5 protection, and commits status changes, role detachment, audit and outbox atomically. Repeated cleanup emits no new transition. The account barrier excludes new Alliance creation and invitation acceptance during deletion.
