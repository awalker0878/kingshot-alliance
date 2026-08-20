# Gift Codes

Status: Current

Gift Codes are owned by `GameWorld/GiftCodes`. The capability maintains one normalized catalogue and one redemption record per Gift Code and Governor.

## User journey

1. A verified account submits a code with an optional source and expiry.
2. The code is normalized so duplicate submissions update the same catalogue entry.
3. A Governor starts redemption for the active Governor or every Governor owned by the account.
4. The application opens Century Games' official Gift Code Center. It does not send Player IDs or codes to an undocumented endpoint.
5. The Governor confirms delivery after checking in-game mail.

The handoff opens the official center in a separate tab and keeps a per-Governor ledger in the application. A successful redemption is terminal: another handoff cannot overwrite it or increment its attempt count. Rate-limited and transient outcomes cannot be retried before their bounded `next_attempt_at` time.

## Outcome model

The ledger supports `awaiting_confirmation`, `redeemed`, `already_redeemed`, `invalid_code`, `expired`, `wrong_kingdom`, `rate_limited`, `transient_failure`, and `permanent_failure`. Retryable provider outcomes receive a bounded exponential retry time.

The UI exposes the last attempt, next eligible retry and one-click continuation to the official center. Submission errors are related to their fields, mutations announce progress/outcome, and repeated clicks are disabled while a request is active.

## Boundaries

- GameWorld owns catalogue, provider policy, and redemption state.
- Communications may deliver new-code announcements from outbox events.
- Provider adapters implement `GiftCodeRedemptionProvider`.
- The default adapter is an official-site handoff, not browser automation or a reverse-engineered API client.

`gift_code.created`, `gift_code.provenance_added`, and `gift_code.status_changed` are global public webhook facts. Each active matching Alliance subscription receives its own signed envelope; no arbitrary source Alliance is attached to the catalogue record.
