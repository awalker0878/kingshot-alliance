# ADR-0004: Gift Code trust from append-only evidence

Status: Accepted — amended for trust-v2

Date: 2026-08-20
Amended: 2026-08-30

## Context

A global Gift Code has two different kinds of state: whether the code is trustworthy for the community and what happened when one Governor attempted to redeem it. Treating those as one status hides conflicting results. Updating canonical source data when a duplicate is submitted also destroys the evidence needed to explain trust decisions.

The original implementation additionally allowed ordinary submissions to claim an `official` source classification and allowed one Governor's negative provider outcome to establish global invalidity/expiry. Those behaviors give account-scoped observations too much global authority and make source labels function as trust claims without a platform-owned source registry.

## Decision

Gift Codes retain separate global and Governor-owned records:

1. `GiftCode` owns normalized identity and the **derived** global trust projection.
2. `GiftCodeProvenance` is append-only source evidence. Fingerprints make an identical observation idempotent; distinct observations remain available for explanation and dispute.
3. `GiftCodeRedemption` owns one Governor's official-provider handoff and observed outcome.
4. `GiftCodeSourceRegistry` is platform-owned source authority. It records source identity, classification, canonical domain, active/revoked state, verification method, provenance policy, and ingestion eligibility.
5. `GiftCodeModerationDecision` is append-only platform review evidence. Corrections append decisions instead of rewriting provenance.

### Source authority

Ordinary submissions may record manual/community provenance but may not assert authoritative `official` provenance. An observation is official only when it is tied to an active registered source and satisfies that source's verification policy.

Legacy rows carrying a user-labelled `official` source type are migrated as **unverified legacy evidence**. Migration never promotes them to verified official evidence.

### Trust-v2 derivation

`ReconcileGiftCodeStatus` remains the only runtime authority allowed to write the derived global trust projection, but trust-v2 changes the evidence rules:

- community evidence begins `pending`;
- one unverified negative Governor report cannot make a code globally invalid, expired, or unavailable;
- a successful Governor redemption is positive evidence but does not convert unrelated Governor-specific facts into global game rules;
- verified official evidence, or a documented independent-evidence threshold, can establish global validity or invalidity;
- credible accepted evidence that materially conflicts produces `disputed`;
- global expiry is derived only from accepted expiry evidence, not whichever submission supplied the earliest date;
- explicit platform quarantine may suppress normal redeemable discovery without deleting the code/evidence;
- every derived state has a stable reason code and supporting evidence references.

The resolver has rollout modes `off`, `shadow`, and `authoritative`. Shadow mode computes trust-v2 and records comparison diagnostics without changing authoritative production state. Trust-v2 cannot become authoritative until legacy classification/backfill, migrations, comparison review, acceptance gates, and documentation reconciliation are complete.

### Monotonic transitions

`GiftCode.status_revision` is monotonic and increments on every material global trust transition. Audit/outbox idempotency uses Gift Code identity plus `status_revision`, not merely the status value. Therefore `valid -> disputed -> valid` produces distinct transition events for revisions N, N+1, and N+2 while replaying revision N+2 remains idempotent.

### Expiry and game facts

Claimed expiry, reward contents, Kingdom/region applicability, and other game facts remain sourced observations until their evidence gates pass. A single `wrong_kingdom` redemption must never become a global Kingdom rule. Unqualified reward details are represented as unknown rather than inferred.

### Moderation authority

Global trust moderation is platform authority only. It requires an MFA-protected platform administrator or a narrowly scoped Gift Code curator grant. Alliance R4/R5 rank does not confer catalogue moderation authority.

Supported decisions include verify, reject, quarantine, restore, correct expiry, and resolve dispute. Required-reason decisions and their supporting evidence are append-only and audited/outboxed.

### Provider boundary

Redemption remains an official-provider handoff. Negative provider evidence is accepted only for a Gift Code/Governor pair with a prior recorded official handoff. No CAPTCHA automation, proxy rotation, undocumented redemption API, provider proxying, or inferred provider result is introduced.

## Consequences

- Duplicate submissions remain visible and cannot silently replace earlier provenance.
- A display label or submitted URL cannot grant official source authority.
- Governor outcomes remain useful evidence without automatically becoming global truth.
- Disputed/quarantined codes preserve evidence and can be explained to affected users.
- Canonical expiry and optional reward/applicability projections are evidence-gated.
- Every material trust transition is individually deliverable and replay-safe through `status_revision`.
- Trust-v2 can be compared against current behavior before authority switches.
- New evidence types update the resolver/evidence gate and behavior fixtures rather than writing `gift_codes.status` directly.

## Rejected alternatives

- Last-submission-wins source updates are rejected because they erase provenance and allow an untrusted duplicate to rewrite a trusted source.
- User-selected `official` classification is rejected because trust authority cannot be delegated through form input.
- A single status shared by the catalogue and every Governor is rejected because one Governor's retry, invalid, expiry, or wrong-Kingdom result does not itself define global validity.
- Earliest-claimed-expiry wins is rejected because an unverified claim can prematurely suppress a globally valid code.
- Status-value-only idempotency is rejected because a return to a prior status is still a new material transition.
- Alliance rank as global catalogue authority is rejected because Alliance governance and platform catalogue governance are separate authority scopes.
- Automated redemption is rejected because the official center is the supported trust boundary and undocumented provider automation would weaken security and operability.
