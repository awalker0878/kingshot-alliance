# Gift Codes

GameWorld owns the Gift Code catalogue and the per-Player, per-Kingdom redemption ledger.

- Code submission is normalized and idempotent. Distinct observations append immutable provenance; duplicates never overwrite the canonical discovery source.
- Shared trust is explicit: `pending`, `valid`, `invalid`, `expired`, or `disputed`. It is derived from expiry and per-Governor evidence by one owner action.
- Redemption is unique per Gift Code and Player.
- Provider outcomes preserve retry, rate-limit, wrong-Kingdom, expired and permanent-failure states.
- The default provider hands Governors to Century Games' official redemption center; it does not call an undocumented API.
- Every submission and redemption transition is recorded in the audit trail and outbox.

Communications may announce Gift Code events, but it does not own Gift Code state or redemption policy.

The UI supports current-Governor, all-owned-Governor, and failed-Governor-only handoff while keeping each result independently auditable. Per-Governor receipts remain visible beside shared source history.

Hourly maintenance reconciles date expiry and queues idempotent 24-hour reminders for Governors with an in-progress redemption. Communications owns delivery preferences and provider attempts; GiftCodes owns who is eligible for an expiry reminder.

Automated providers must preserve the same outcome vocabulary and may never bypass Player ownership checks.
