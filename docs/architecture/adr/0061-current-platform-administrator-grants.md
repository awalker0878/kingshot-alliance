# ADR-0061: Current Platform Administrator grants

Status: Accepted

## Context

Administrator grant locked its actor grant before its target grant, while revocation locked both in ID order. Crossed commands could form a cycle. The target account was checked only for existence before the transaction, so access could attach to an already finalized account or race the deletion owner's active-administrator blocker. Concurrent first grants to the same target also lacked an existing row to lock.

## Decision

Platform Administration extends its existing PostgreSQL transaction coordinator from bootstrap to every grant and revocation. The renamed PlatformAdministratorMutationCoordinator owns the same advisory key and is acquired before grant rows. This serializes the small privileged catalogue, including absent target rows and bootstrap, without serializing ordinary Platform writes or game-domain requests. Grant rows still protect current authority against other Platform actions; revocation retains its deterministic row order and self-revocation restriction.

Grant additionally calls the Accounts owner to lock and validate the current active target account before returning an existing grant or creating/restoring access. Account deletion holds this same account row while evaluating blockers and finalizing. If grant commits first, deletion sees active administrator access and remains blocked. If deletion commits first, grant rejects the finalized account.

Target account acquisition uses NOWAIT because another administrative path can hold grant authority before an account lock. A busy account aborts the complete command and yields a retryable form/CLI validation message. The HTTP adapter maps target errors to the existing email field, which remains visible alongside the retained input. Accounts owns active-account interpretation; no User model or lifecycle predicate is copied into Platform.

The coordinator replaces the bootstrap-only implementation; no alias or SQLite bypass remains. Grants, revocations and their audit records stay in one owner transaction. Bootstrap still requires the absence of all active grants, normal grants require current actor authority, repeats produce no new audit, and Platform access grants no game authority.

## Alternatives and verification

Sorting only existing grant rows does not protect absent target grants or bootstrap. Acquiring the target account without a current-state barrier retains the finalization race. Waiting for it after grant authority introduces another opposing dependency with legal holds. The existing low-volume coordinator plus nonwaiting Accounts barrier addresses these concrete dependencies without a new table or foreign persistence.

PlatformAdministratorConcurrencyTest covers crossed grant/revoke orders, two first-grant/ bootstrap contenders, both grant/finalization orders and late audit failure with rollback and retry. Existing administrator isolation, authenticated admission, explicit bootstrap/revoke and deletion suites remain required containing coverage. HARD-118 records executed evidence.
