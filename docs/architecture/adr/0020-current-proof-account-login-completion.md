# ADR-0020: Complete account login against current credential proof

Status: Accepted

## Problem

Credential validation and session registration previously occurred in separate uncoordinated operations. A credential change could revoke every known session after a password/provider/passkey was verified, then an in-flight login could register a new unrevoked session from that earlier proof. MFA completion held a database transaction while the maintained session guard destroyed raw session storage, and late audit failures could leave transient authentication behind.

## Decision

Accounts owns explicit login completion. Primary authentication captures an immutable VerifiedAccountLogin containing account ID, selected method/reference and fingerprints of existing credential, remember-token and MFA state. AccountLoginProofs interprets those fingerprints against the current locked account and selected credential. No separate mutable authentication version or second credential authority is introduced.

CompleteAccountLogin has a bounded sequence:

1. Check current active account, selected proof and any required second factor under the account lock. Prepare an absent remember token only in memory.
2. Close that transaction and let the maintained session guard prepare fixation protection and any remembered cookie. This external session operation holds no database lock.
3. Lock the account again, recheck the original proof, consume a recovery code if used, persist an initial remember token and commit the final session registration with login audit.
4. Publish maintained Login/Authenticated events through Event.defer after completion succeeds, then clear the pending challenge and mark recent authentication. Report event-consumer failures that occur after durable commit.

A failure before durable completion discards buffered login events, clears prepared authentication and queued remembered cookies, and preserves a still-current MFA challenge/code for retry. A changed credential/account proof clears the obsolete challenge. Login completion rejects an enclosing database transaction so a caller cannot retain a lock across external storage or later roll back an already authenticated request.

Password verification and optional rehash use the maintained user provider under the account lock. WebAuthn remains maintained-package validation; its account adapter establishes account-first lock order. OAuth verification remains with Socialite. HTTP adapters retain redirects and validation presentation; owner completion retains persistence and assurance decisions. Onboarding still commits its dependent registration/claim/membership composition before login completion begins.

## Consequences and verification

Revocation that commits during raw session preparation invalidates the final proof check. Revocation that waits behind final completion sees the newly registered session. Recovery-code use and initial remember-token persistence roll back with failed login audit or session registration. Session storage remains an external system: a later response-save failure can leave an unused durable session row, but cannot make a revoked row authoritative again.

AccountLoginCompletionV3Test exercises real HTTP guard events/remember initialization, actual late SQL/storage failures with retry, competing PostgreSQL credential/lifecycle transitions during rotation and revocation during final registration. MFA integration tests require real commits instead of a surrounding fixture transaction. PasskeyConfirmationAtomicityV3Test exercises maintained signed WebAuthn assertions, counter rollback and account-first locking.

The password/MFA slice introduces this owner. Migration of Google primary login, registration auto-login, remembered sessions, passkey login and logout is tracked in HARD-037; the decision does not claim those integrations or the repository-wide audit are complete.
