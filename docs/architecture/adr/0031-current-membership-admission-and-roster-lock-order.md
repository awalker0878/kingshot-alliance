# ADR-0031: Coordinate current membership admission and roster writes

Status: Accepted

## Context

Invitation onboarding claimed Player identity before acquiring its existing Alliance scope. Existing roster updates acquired the roster entry before Player, while departures used the reverse order. Membership activation read unlocked Player placement and locked other Alliances' membership rows after acquiring its own scope. Concurrent activations could both observe no active membership and expose a unique-constraint error.

## Decision

Existing-Alliance admission follows account, Alliance, active Kingdom, Player and scoped dependent records. The Alliance-owned InvitationAcceptanceScope acquires the current Alliance and active Kingdom before onboarding calls the GameWorld claim owner. AcceptInvitation revalidates the scope and current invitation within the same enclosing transaction. Its contract accepts account, token and Player identity only; Accounts and GameWorld provide current email, lifecycle, ownership and Kingdom facts. Claim history, membership, invitation status, audit and delivery remain atomic with registration or existing-account onboarding.

Administrative activation retains its exclusive Alliance scope and current administrator membership. It then locks the target membership and acquires a shared current canonical Player reference. This stabilizes identity against movement, release and reconciliation without acquiring rows belonging to another Alliance. The exclusive Alliance barrier excludes same-Alliance acceptance and roster writers before their differently scoped child locks can conflict. Across Alliances, activation observes active membership without locking foreign membership records. Concurrent shared Player readers may both attempt activation; the unique active-membership index chooses the winner. The losing save runs inside a savepoint, then translates a verified active-membership conflict into validation while preserving a usable caller transaction. Other database failures propagate. Acceptance uses the same conflict recovery.

Roster updates and departures discover routing without locking, acquire the current Player, then lock the entry constrained to the same Alliance and discovered Player. Changed routing is rejected before mutation. This ordering permits independent administrators to work on separate entries while serializing conflicting changes to one Player.

## Alternatives

Acquiring Player before an existing Alliance would conflict with established roster and cleanup writers. Exclusive Player locking for every administrative activation would serialize competing admission but would not remove foreign-scope membership dependencies by itself. Locking an absent active membership does not protect the absence. Catching a PostgreSQL constraint failure without a savepoint leaves the transaction unusable. Duplicating claim/history logic inside Alliance would violate GameWorld ownership.

## Consequences and verification

Creation still locks account, Kingdom and Player before inserting a new Alliance; it never waits on an existing Alliance scope. Atomic account deletion retains account, ordered existing Alliance scopes, membership cleanup and Player release. Administrative membership remains attached to durable Player identity and does not require current account ownership; admission through an invitation requires a current claimed Player owned by the accepting account.

Separate PostgreSQL connection tests cover both activation winners, creation and acceptance against activation, movement/release/reconciliation, roster update/departure ordering, independent entries, changed routing, invitation revocation, Alliance suspension and account deletion. Owner tests reject stale email, ownership, terminal account and archived Kingdom facts. Late audit/outbox failures preserve full transaction rollback. Runtime results and remaining verification are recorded in the hardening delivery ledger.
