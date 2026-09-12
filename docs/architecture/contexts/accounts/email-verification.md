# Accounts — EmailVerification

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/EmailVerification`

EmailVerification owns verified-email state and verification flows for User accounts.

It is an account-assurance capability. Verification does not itself grant game-domain permissions; game authority continues to derive from the active Player and the owning game context.

Registration, resend and pending-address verification use RequestEmailVerification to record a private account.email.verification_requested outbox intent while holding the account lock. The User notification hook queues this intent; it never contacts mail. Registration records it inside the same outer onboarding transaction, so a later owner rejection rolls it back. The user.registered business event remains independent from this delivery command.

The Accounts outbox consumer sends the existing branded Laravel verification notification after the publisher commits its claim. It rechecks account existence, terminal lifecycle, verification target, current state and the requested address fingerprint immediately before sending. Missing/finalized accounts and changed or cleared target addresses consume stale intent without delivery. Already-verified account addresses also suppress account verification, while a pending address still requires its own proof. Payloads contain no raw address, password or signed link; the maintained signature and expiry are generated at delivery time.

SMTP failure propagates to the existing durable outbox retry/backoff and administrator recovery controls. Successful enqueueing is not proof of mail delivery. Transport execution is at least once: a failure after SMTP acceptance but before acknowledgement may send a duplicate, and an already-running external send cannot be retracted by a later account transition.

Email promotion records its old-address notice through EmailChangedNoticeOutbox inside the profile transaction. The private outbox command encrypts both the historical recipient and the address that was promoted; the consumer preserves those historical facts even if another email change occurs before delivery. These private events are outside the public webhook catalogue. Account finalization deletes their encrypted payload records, including already-published notices; a consumer also purges them if it observes a missing/finalized account. The normal active-account outbox retains the encrypted command for transport history. No second queue/retry authority or generic mail framework is introduced.

Branded account mail supplies one shared data set to its HTML and plain-text views. HTML escapes content; the plain-text view emits literal text and URLs so query separators remain valid when the user follows verification/reset links.

Verification completion delegates to VerifyAccountEmail. The route retains the maintained signed request and account/hash authorization; the owner then locks the current active User and revalidates the current address hash before calling Laravel's verification primitive. The verified flag and audit event commit together. The framework Verified event waits for the outermost commit, is discarded on rollback and is not repeated for an already-verified address.

The authenticated recent-proof email-change route uses the named account-email-change limiter: six attempts per account per minute. Changing client IP does not create a new budget. Rejected requests reach neither the pending-address mutation nor its delivery/security intent; another account retains an independent budget, and normal requests resume after the window.
