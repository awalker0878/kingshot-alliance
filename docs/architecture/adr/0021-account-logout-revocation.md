# ADR-0021: Revoke remembered authority and complete browser cleanup on sign-out

Status: Accepted

## Problem and superseded implementation

The first LogoutAccount slice revoked the current durable session but preserved the account remember token. A copy of that browser's old remember cookie could therefore restore a different session after sign-out. The guard's CurrentDeviceLogout event also runs before its in-memory user is cleared; listener failure could interrupt session invalidation. Raw storage exceptions could prevent session-ID regeneration.

## Decision and alternatives

Keep the maintained account-scoped remember contract. Rotate an existing remember token inside the account-locked transaction with current-session revocation and logout audit. Other active sessions remain valid, while all old account remember cookies are invalidated. Preserving other remembered cookies would require per-device persistent credentials and their own lifecycle; that is not introduced just to retain replayable credentials. Calling the guard's full logout would rotate authority outside the owner lock, so it is not used.

After the owner transaction, invoke maintained current-device guard cleanup. Defer its event until browser cleanup completes. Always clear the in-memory guard and flush session data; attempt raw storage deletion once outside database locks, report false returns/exceptions, and generate a new session ID and CSRF token without retrying destruction. Discard the buffered success event when durable persistence fails. Report late listener failure without undoing committed revocation.

## Ownership, security, scalability and operations

Accounts remains the only authority for session revocation and remember state. Work is bounded to one account, one current session and one raw-storage cleanup. Account-first locking serializes remember changes with current-proof login and MFA transitions. Committed durable revocation defeats a late stale storage writer; failed persistence is not represented as successful revocation. This is a fresh deployment and requires no compatibility schema, version field or alternate token model.

## Verification

AccountLogoutV3Test covers retained other active sessions, atomic token/audit rollback, suppressed success events on rollback, password/provider remember-cookie replay, false/throwing raw deletion with stale-session replay, throwing event consumers and enclosing-transaction rejection. Containing PostgreSQL and architecture/static/style evidence belongs in HARD-044 in the delivery ledger.
