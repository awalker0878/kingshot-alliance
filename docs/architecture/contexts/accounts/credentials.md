# Accounts — Credentials

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Credentials`

Credentials owns password and account-credential lifecycle behavior, including change/reset operations and their security invariants.

Credential writes are implemented by capability Actions rather than controllers. Password/recovery material is never written to logs, audit payloads or documentation.