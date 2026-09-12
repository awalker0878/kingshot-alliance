# ADR-0019: Atomic owner composition for account onboarding

Status: Accepted

## Problem

AccountOnboarding previously committed Accounts registration, GameWorld Player claiming and Alliance invitation acceptance independently. Acceptance could reject the account email, current roster or Alliance lifecycle after Player ownership had already changed. Registration failures could also leave a new account and verification email behind.

The blanket Workflow transaction prohibition prevented a single dependent database command from preserving its success/failure contract. Adding preflight checks would not protect against changes between owner calls. Moving foreign persistence into one context or hiding the transaction in a generic callback wrapper would obscure ownership.

## Decision

`RegisterAccount` and `AcceptInvitationForAccount` may open a bounded database transaction around their dependent owner calls. They are the two explicit exceptions registered in the architecture verifier. No other Workflow gains this permission.

- Accounts remains the sole writer of accounts, provider identities and registration audit/outbox. Registration verification intent commits with onboarding; the Accounts outbox consumer delivers after the outermost commit with durable retry.
- Existing-account acceptance obtains the current account snapshot through the Accounts locking query and rejects finalized accounts. Account email/lifecycle cannot change during acceptance.
- GameWorld locks, validates and claims the targeted Player through its existing owner Action.
- Alliance locks and validates the current invitation, lifecycle, roster and membership through its existing owner Action. A rejection rolls back the Player claim and every preceding owner write in the composed command.
- Registration delegates invitation acceptance to the same Workflow Action used by existing accounts, eliminating the duplicated sequence.

The Workflow owns only the atomic composition decision. It cannot import context models, perform SQL/persistence, acquire business locks directly, interpret foreign permissions, or add aggregate repositories/tables. Owner Actions retain their transactions, locks, audit and outbox writes. All participating writes use the same configured database connection; remote effects are outside the transaction.

## Alternatives considered

- Independent commits plus preflight checks retain partial writes and race windows.
- Compensating a Player claim after failure exposes temporary unauthorized ownership and risks undoing concurrent legitimate work.
- A distributed process/outbox is appropriate for independently committed or remote operations, but adds avoidable intermediate authority states to this bounded single-database command.
- Moving foreign models into an owner or a shared transaction callback hides the same composition without preserving a clear boundary.

## Verification and consequences

`AtomicAccountOnboardingV3Test` exercises real owner Actions and HTTP acceptance. It covers wrong-email and inactive-Alliance denial; password/Google registration rollback after lifecycle, roster or ownership rejection; revocation committed through a second PostgreSQL connection after preflight; durable verification intent and worker delivery only after all owners commit; successful existing-account acceptance and replay; and finalized-account rejection.

The architecture verifier continues to reject direct Workflow persistence, model imports, business locks and permission vocabularies, and rejects transactions outside the two named commands. New atomic compositions require their own justification and failure/rollback evidence. NotificationDelivery remains without transactions.

This decision replaces the blanket no-transaction rule only for these two onboarding commands. It does not change the read-only ReadModel or thin HTTP rules.
