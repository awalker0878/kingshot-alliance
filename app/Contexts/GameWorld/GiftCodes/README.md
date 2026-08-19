# Gift Codes

GameWorld owns the Gift Code catalogue and the per-Player, per-Kingdom redemption ledger.

- Code submission is normalized and idempotent.
- Redemption is unique per Gift Code and Player.
- Provider outcomes preserve retry, rate-limit, wrong-Kingdom, expired and permanent-failure states.
- The default provider hands Governors to Century Games' official redemption center; it does not call an undocumented API.
- Every submission and redemption transition is recorded in the audit trail and outbox.

Communications may announce Gift Code events, but it does not own Gift Code state or redemption policy.
