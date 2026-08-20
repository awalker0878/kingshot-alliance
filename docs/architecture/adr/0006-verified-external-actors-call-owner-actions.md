# ADR-0006: Verified external actors call owner actions

Status: Accepted

Date: 2026-08-20

## Context

Discord and Telegram adapters need self-service write parity with the web application. Trusting a Player ID in an API payload would allow an integration client to choose its actor. Reimplementing Event response, capacity or waitlist behavior in bot code would create divergent domain rules. Ordinary retry behavior can also repeat a successfully committed chat command after a timeout.

## Decision

Platform/Integrations owns external-actor pairing and action receipts:

1. An authenticated active Alliance member generates a provider-specific, high-entropy code that expires after ten minutes and invalidates older unused codes for that provider.
2. A scoped Alliance API credential claims the code with a stable Discord or Telegram user ID. Only keyed hashes and a short display hint are persisted.
3. Every actor write resolves an active Alliance/provider link server-side. The API never accepts a Player ID as authority.
4. Every normalized write requires an `Idempotency-Key`. Platform reserves a receipt inside the transaction, returns the stored result for an exact replay and rejects key reuse with different input.
5. `Workflows/ExternalEventParticipation` owns the multi-context HTTP adapter and coordinates Platform identity with Operations/Participation owner actions. Context packages never depend upward on the workflow. Operations continues to decide Event capability, self authorization, registration windows, capacity and waitlist behavior.
6. Link revocation, API credential state and scope are revalidated before each write.

## Consequences

- Provider adapters remain transport clients rather than business-rule owners.
- A leaked provider user ID is insufficient to act as a Governor.
- Timeout retries do not duplicate domain writes or audit evidence.
- Members can replace or revoke connections without manager intervention.
- Platform retains privacy-safe machine-action evidence while Operations retains the authoritative participation record.

## Rejected alternatives

- Accepting `player_id` from the adapter was rejected because API credential possession is not Player authority.
- Storing raw provider user IDs was rejected because equality lookup can use a keyed hash.
- Implementing RSVP rules in each bot was rejected because it creates incompatible validation and recovery behavior.
- Treating naturally idempotent database updates as sufficient was rejected because audits, outbox effects and registration transitions can still repeat.
