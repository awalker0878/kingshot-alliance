# Accounts — Credentials

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Credentials`

Credentials owns password and account-credential lifecycle behavior, including change/reset operations and their security invariants.

Credential writes are implemented by capability Actions rather than controllers. Password/recovery material is never written to logs, audit payloads or documentation.

Adding, removing or changing a password commits the credential, audit, durable revocation of other browser sessions and Communications security intent in the same account-locked owner transaction. The caller explicitly identifies the session to preserve; null means no session is preserved. Password removal consumes outstanding reset tokens and revokes API tokens in that transaction. Communications delivery remains asynchronous; an intent persistence failure rolls back the credential transition.

Password changes write the hash once through the owner Action. The HTTP adapter refreshes its authenticated User for Laravel session-hash tracking and clears recent proof after success. It does not call logoutOtherDevices, whose forced rehash would write the credential again outside the owner lock. Session storage cleanup runs only after the outermost commit; cleanup errors are reported while durable revocation remains authoritative. A rolled-back outer mutation discards the pending cleanup.
