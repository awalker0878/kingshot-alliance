# Accounts — Credentials

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Credentials`

Credentials owns password and account-credential lifecycle behavior, including change/reset operations and their security invariants.

Credential writes are implemented by capability Actions rather than controllers. Password/recovery material is never written to logs, audit payloads or documentation.

Adding, removing or changing a password commits the credential, audit, durable revocation of other browser sessions and Communications security intent in the same account-locked owner transaction. The caller explicitly identifies the session to preserve; null means no session is preserved. Password removal consumes outstanding reset tokens and revokes API tokens in that transaction. Communications delivery remains asynchronous; an intent persistence failure rolls back the credential transition.

Password changes write the hash once through the owner Action. The HTTP adapter refreshes its authenticated User for Laravel session-hash tracking and clears recent proof after success. It does not call logoutOtherDevices, whose forced rehash would write the credential again outside the owner lock. Session storage cleanup runs only after the outermost commit; cleanup errors are reported while durable revocation remains authoritative. A rolled-back outer mutation discards the pending cleanup.

Password reset locks the current account before invoking the maintained broker. The broker's token validation, credential callback and token consumption run inside that owner transaction. Accounts rechecks current password availability and terminal lifecycle, writes the password once, revokes API/browser/remember credentials and persists audit/security intent. A later intent or token-consumption failure rolls everything back; the PasswordReset framework event and raw session cleanup run only after the outermost commit. Invalid/expired/consumed tokens and changed account state cannot report reset success.

RequestPasswordReset is the issuance owner. It normalizes the address, locks/rechecks the active password-bearing account, and runs maintained broker generation/throttling plus delivery intent within that transaction. Missing, finalized and password-less identities preserve the generic HTTP response and the same minimum timebox. Callers use this owner rather than invoking broker issuance directly. The User notification hook delegates to QueuePasswordResetDelivery, which rechecks the current account and maintained token validity; repeated notification hooks for the same issued token are no-ops.

Pending delivery material lives only on the existing password_reset_tokens row, beside the maintained verifier hash: a unique delivery ID and encrypted delivery token. The outbox payload contains only that ID, scoped to the User aggregate; it never contains the recovery secret, encrypted secret or recipient. The consumer locks the current account, validates scope/current delivery/token/expiry through Laravel, commits that check, then sends branded mail outside transactions. It clears encrypted delivery material after a successful send while keeping the verifier hash redeemable. Superseded, consumed and ineligible tokens suppress delivery. SMTP errors enter normal outbox backoff with a fixed sanitized message and no original exception cause that could expose the recovery URL. The framework PasswordResetLinkSent event occurs after delivery, not enqueueing.

Password addition/change/removal, reset consumption, email promotion and finalization invalidate the maintained token row within their current account transaction, also removing pending encrypted material. The centrally scheduled Laravel auth:clear-resets command runs hourly, using the indexed created_at expiry predicate; this bounds expired delivery material even if the outbox exhausts retries. No additional token authority or delivery queue is introduced. Delivery remains at least once: a worker failure between SMTP acceptance and clearing the encrypted field can repeat mail; a send already in progress cannot be recalled by a later credential transition.
