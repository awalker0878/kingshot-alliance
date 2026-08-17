# Accounts — EmailVerification

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/EmailVerification`

EmailVerification owns verified-email state and verification flows for User accounts.

It is an account-assurance capability. Verification does not itself grant game-domain permissions; game authority continues to derive from the active Player and the owning game context.