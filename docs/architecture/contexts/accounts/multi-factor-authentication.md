# Accounts — MultiFactorAuthentication

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/MultiFactorAuthentication`

MultiFactorAuthentication owns MFA/TOTP enrollment, challenge and recovery behavior for User accounts.

MFA is account assurance. It does not replace Player-scoped authorization for game capabilities. Secrets and recovery codes remain sensitive and must not be logged or exposed in audit/event payloads.

MFA confirmation, recovery-code regeneration and disabling persist their security notification intent inside the account-locked owner transaction, alongside credential/audit/outbox state where applicable. Only committed recovery-code changes return plaintext codes to the caller; delivery runs through Communications after commit.
