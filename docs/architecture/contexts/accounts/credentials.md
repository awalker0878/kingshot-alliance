# Accounts — Credentials

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Credentials`

Credentials owns password and account-credential lifecycle behavior, including change/reset operations and their security invariants.

Credential writes are implemented by capability Actions rather than controllers. Password/recovery material is never written to logs, audit payloads or documentation.

Adding or removing a password commits the credential, audit and Communications security intent in the same owner transaction. Password removal consumes outstanding reset tokens and revokes API tokens in that transaction. Communications delivery remains asynchronous; an intent persistence failure rolls back the credential transition.
