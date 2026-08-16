# Accounts — MultiFactorAuthentication

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/MultiFactorAuthentication`

MultiFactorAuthentication owns MFA/TOTP enrollment, challenge and recovery behavior for User accounts.

MFA is account assurance. It does not replace Player-scoped authorization for game capabilities. Secrets and recovery codes remain sensitive and must not be logged or exposed in audit/event payloads.