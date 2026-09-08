# Accounts — MultiFactorAuthentication

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/MultiFactorAuthentication`

MultiFactorAuthentication owns MFA/TOTP enrollment, challenge and recovery behavior for User accounts.

MFA is account assurance. It does not replace Player-scoped authorization for game capabilities. Secrets and recovery codes remain sensitive and must not be logged or exposed in audit/event payloads.

MFA confirmation, recovery-code regeneration and disabling persist their security notification intent inside the account-locked owner transaction, alongside credential/audit/outbox state where applicable. Each confirmed assurance transition also rotates remember authority in that transaction, invalidating cookies issued under the prior MFA state while deliberately preserving the current authenticated browser session. Rollback preserves the previous MFA and remember state. Only committed recovery-code changes return plaintext codes to the caller; delivery runs through Communications after commit.


A primary sign-in challenge carries the selected credential reference and fingerprints of current credential/account authentication state, with a ten-minute lifetime. CompleteMfaLogin reconstructs that bound proof and delegates to Accounts CompleteAccountLogin. Recovery verification before raw session preparation is read-only; recovery consumption, session registration and authentication audit commit atomically after the final current-proof check. An invalid second factor does not create a session, emit Login, initialize a remember token or consume a code. Completion failures retain a still-current challenge and code for retry; credential/lifecycle mismatch clears the obsolete challenge.
