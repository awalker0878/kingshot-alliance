# Accounts — EmailVerification

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/EmailVerification`

EmailVerification owns verified-email state and verification flows for User accounts.

It is an account-assurance capability. Verification does not itself grant game-domain permissions; game authority continues to derive from the active Player and the owning game context.

Registration and resend use RequestEmailVerification to record a private account.email.verification_requested outbox intent while holding the account lock. The User notification hook queues this intent; it never contacts mail. Registration records it inside the same outer onboarding transaction, so a later owner rejection rolls it back. The user.registered business event remains independent from this delivery command.

The Accounts outbox consumer sends the existing branded Laravel verification notification after the publisher commits its claim. It rechecks account existence, terminal lifecycle, verification state and the requested address fingerprint immediately before sending. Missing, finalized, verified or changed-address accounts consume stale intent without delivery. Payloads contain no raw address, password or signed link; the maintained signature and expiry are generated at delivery time.

SMTP failure propagates to the existing durable outbox retry/backoff and administrator recovery controls. Successful enqueueing is not proof of mail delivery. Transport execution is at least once: a failure after SMTP acceptance but before acknowledgement may send a duplicate, and an already-running external send cannot be retracted by a later account transition.
