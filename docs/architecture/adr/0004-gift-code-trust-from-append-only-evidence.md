# ADR-0004: Gift Code trust from append-only evidence

Status: Accepted — canonical fresh-schema design

Date: 2026-08-20
Amended: 2026-08-30

## Context

A global Gift Code has two different kinds of state: whether the code is trustworthy for the community and what happened when one Governor attempted to redeem it. Treating those as one status hides conflicting results. Updating canonical source data when a duplicate is submitted also destroys the evidence needed to explain trust decisions.

The pre-extension implementation allowed ordinary submissions to claim an `official` source classification and allowed one Governor's negative provider outcome to establish global invalidity/expiry. Those behaviors give account-scoped observations too much global authority and make user-controlled source labels function as trust claims.

This program is deployed with a fresh database schema. The repository therefore does not preserve a legacy Gift Code resolver, data backfill, shadow-comparison mode, compatibility route, or transitional schema alongside the final design.

## Decision

Gift Codes use separate global, evidence, and Governor-owned records:

1. `GiftCode` owns normalized identity and the **derived** global trust projection only.
2. `GiftCodeProvenance` is append-only source evidence. Fingerprints make an identical observation idempotent; distinct observations remain available for explanation and dispute.
3. `GiftCodeRedemption` owns one Governor's official-provider handoff and observed outcome.
4. `GiftCodeSourceRegistry` is platform-owned source authority. It records source identity, classification, canonical domain, active/revoked state, verification method, provenance policy, and ingestion eligibility.
5. `GiftCodeModerationDecision` is append-only platform review evidence. Corrections append decisions instead of rewriting provenance.

### Canonical schema

The Gift Code create migration directly defines the current schema. `gift_codes` does not duplicate source type, source label, source URL, user claims, or migration-comparison state. Those facts live in append-only evidence or platform source records.

There is no Gift Code ALTER/backfill migration whose purpose is upgrading the prior Gift Code schema. Fresh redeployment recreates this schema directly.

### Source authority

Ordinary submissions record unverified community/manual provenance but cannot assert authoritative official provenance. An observation is authoritative only when it is tied to an active registered source and satisfies that source's verification policy, or when the documented independent-evidence threshold is met.

Display labels and submitted URLs are metadata, never authority.

### Canonical trust derivation

`ReconcileGiftCodeStatus` and `GiftCodeTrustResolver` are the only runtime path allowed to write the derived global trust projection.

The evidence rules are:

- community/manual evidence begins `pending`;
- one unverified negative Governor report cannot make a code globally invalid, expired, or unavailable;
- successful Governor redemptions may contribute independent positive evidence but do not turn Governor-specific facts into global game rules;
- qualified authoritative evidence, or a documented independent-evidence threshold, can establish global validity or invalidity;
- credible accepted evidence that materially conflicts produces `disputed`;
- global expiry is derived only from accepted expiry evidence;
- conflicting qualified expiry claims produce `disputed` until resolved;
- explicit platform quarantine suppresses normal redeemable discovery without deleting the code/evidence;
- every derived state has a stable reason code and supporting evidence references.

There is no legacy resolver, no `off/shadow/authoritative` dual-run mode, and no comparison columns. The canonical resolver is authoritative by design.

### Monotonic transitions

`GiftCode.status_revision` increments on every material global trust transition. `GiftCode.expires_revision` increments on every material canonical expiry change. Audit/outbox idempotency uses Gift Code identity plus the applicable revision, not merely the status value.

Therefore `valid -> disputed -> valid` produces distinct transition events for revisions N, N+1, and N+2 while replaying an unchanged revision remains idempotent.

### Expiry and game facts

Claimed expiry, reward contents, Kingdom/region applicability, and other game facts remain sourced observations until their evidence gates pass. A single `wrong_kingdom` redemption must never become a global Kingdom rule. Unqualified reward details are represented as unknown rather than inferred.

### Moderation authority

Global trust moderation is platform authority only. It requires an MFA-protected platform administrator or a narrowly scoped Gift Code curator grant. Alliance R4/R5 rank does not confer catalogue moderation authority.

Supported decisions include verify, reject, quarantine, restore, correct expiry, and resolve dispute. Required-reason decisions and supporting evidence are append-only and audited/outboxed.

### Provider boundary

Redemption remains an official-provider handoff. Negative provider evidence is accepted only for a Gift Code/Governor pair with a prior recorded official handoff. No CAPTCHA automation, proxy rotation, undocumented redemption API, provider proxying, or inferred provider result is introduced.

### Compatibility policy

The fresh-schema deployment does not keep deprecated Gift Code compatibility paths. When the extension replaces a Gift Code route, API endpoint, schema field, resolver, or action, the superseded implementation is deleted. Compatibility must never be used as a reason to retain weaker authorization or trust behavior.

## Consequences

- Duplicate submissions remain append-only evidence and cannot silently replace earlier provenance.
- A display label or submitted URL cannot grant official source authority.
- Governor outcomes remain useful evidence without automatically becoming global truth.
- Disputed/quarantined codes preserve evidence and can be explained to affected users.
- Canonical expiry and optional reward/applicability projections are evidence-gated.
- Every material trust and expiry transition is independently deliverable and replay-safe through revisions.
- New evidence types update the resolver/evidence gate and behavior fixtures rather than writing `gift_codes.status` directly.
- The repository has one Gift Code trust implementation rather than a permanent migration/compatibility layer.

## Rejected alternatives

- Last-submission-wins source updates are rejected because they erase provenance and allow an untrusted duplicate to rewrite a trusted source.
- User-selected `official` classification is rejected because trust authority cannot be delegated through form input.
- A single status shared by the catalogue and every Governor is rejected because one Governor's retry, invalid, expiry, or wrong-Kingdom result does not itself define global validity.
- Earliest-claimed-expiry wins is rejected because an unverified claim can prematurely suppress a globally valid code.
- Status-value-only idempotency is rejected because a return to a prior status is still a new material transition.
- Alliance rank as global catalogue authority is rejected because Alliance governance and platform catalogue governance are separate authority scopes.
- Dual-running legacy and canonical trust resolvers is rejected for this fresh-schema deployment because there is no production Gift Code data migration requirement.
- Deprecated Gift Code route/API compatibility aliases are rejected because they add maintenance and authorization surface without a deployment need.
- Automated redemption is rejected because the official center is the supported trust boundary and undocumented provider automation would weaken security and operability.
