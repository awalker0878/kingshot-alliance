# ADR-0004: Gift Code trust from append-only evidence

Status: Accepted

Date: 2026-08-20

## Context

A global Gift Code has two different kinds of state: whether the code is trustworthy for the community and what happened when one Governor attempted to redeem it. Treating those as one status hides conflicting results. Updating the canonical source when a duplicate is submitted also destroys the evidence needed to explain trust decisions.

## Decision

Gift Codes use three related records with separate ownership:

1. `GiftCode` owns the normalized code and the derived trust state: `pending`, `valid`, `invalid`, `expired`, or `disputed`.
2. `GiftCodeProvenance` is append-only source evidence. A fingerprint makes an identical observation idempotent; a distinct source is retained instead of overwriting the canonical discovery record.
3. `GiftCodeRedemption` owns one Governor's provider handoff and outcome history.

`ReconcileGiftCodeStatus` is the only runtime authority for derived trust transitions. A confirmed redemption makes a non-expired code valid. Invalid or expired provider evidence makes it invalid or expired. Conflicting successful and negative evidence makes it disputed. The configured expiry date dominates once it passes. Every trust change emits audit and outbox evidence.

Redemption remains an official-provider handoff. No CAPTCHA automation, proxy rotation, undocumented game-client call, or inferred provider result is introduced.

## Consequences

- Duplicate submissions are visible and cannot silently replace earlier provenance.
- The UI can show shared trust and per-Governor outcomes without conflating them.
- A disputed code remains visible and redeemable with an explicit warning while its source history is inspectable.
- Invalid and expired codes cannot begin new handoffs.
- Scheduled maintenance applies time-based expiry and queues idempotent reminders only for Governors with an in-progress redemption.
- New evidence types must update the resolver and its behavior fixtures rather than writing `gift_codes.status` directly.

## Rejected alternatives

- Last-submission-wins source updates were rejected because they erase provenance and allow an untrusted duplicate to rewrite a trusted source.
- A single status shared by the catalogue and every Governor was rejected because one Governor's retry or wrong-Kingdom result does not define global validity.
- Automated redemption was rejected because the official center is the supported trust boundary and undocumented provider automation would weaken security and operability.
