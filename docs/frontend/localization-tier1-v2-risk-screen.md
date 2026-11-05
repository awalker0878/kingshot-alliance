# Tier 1 candidate screen — proposed full-source v2 (DRAFT)

**Baseline:** immutable `7cf387b628bbbaeb81d10120340e559b46403bee`; PR #180's merged `codex/audit-stabilization` at `a5151e717020cfe00a32aeddcca6279f9c7042fe`. The prior script `scripts/report-localization-tier1a.mjs` and its original **511** reproducible candidates are unchanged.

## Why the original screen missed known critical messages

The original Tier 1 report applies strict content keywords **only to 1,985 entries already selected by broad regexes**, even though the English source has **6,812** messages. A rule added only to the strict stage cannot see an entry rejected upstream.

Concrete misses: `notifications.statuses.pending` (state key `pending` but winner text `Sending`); `notifications.bulk.confirmDescription` (bulk confirmation implied by key rather than restricted content keywords); `notifications.deliverySecurity` (encryption, never returned to browser, Governor scope claim). `notifications.bulk.previewSummary` enters the broad set but fails the old strict keywords. `notifications.types.giftCodeExpiring` also enters broad but requires explicit expiry detection.

## Revised screening mechanics and evidence

`scripts/report-localization-tier1a-v2.mjs` recomputes the original 511 from the original 1,985 broad-flagged messages, then **unions** key-and-content-aware state transition, security claim, confirmation, and expiry criteria over **all 6,812 pinned English messages**. Inputs are SHA-256 verified against the committed fingerprint manifest, including deterministic calibration sample hashes.

**Provisional, locally replayed count: 613 = 511 previous + 102 additional unique candidates.** Distribution: **329 client/frontend**, **251 server JSON**, and **33 account PHP**. The newly surfaced 102 include **92 client** and **10 server JSON** entries; no account PHP candidates are removed. No source strings are changed. Exact candidates and rule provenance are emitted to the CI artifact.

The finite manual 41-key Recipient Delivery audit exposed multiple misses, but **3/41 is not a statistical per-locale false-negative rate**, because those keys were selected through high-impact collision review, not a random representative source-language sample. The formal 32-per-locale calibration consists of **512 deterministic unflagged translations**, all awaiting native-language decisions. The report records **0 reviewed / 32 unreviewed per locale; estimatedMissRate = null**, never a fabricated zero. The revised rules newly match one sampled source each in Spanish and Indonesian calibration strata; these are **screening hits, not reviewer-confirmed misses**.

## Acceptance and recruitment implications

- [ ] Workflow passes on the exact final head and reports all 613 with verified pinned input hashes.
- [ ] Product, security and localization owners review false positives and false negatives, including confirmation-key scope and lifecycle status semantics.
- [ ] Assign 32 samples per locale for qualified review and compute a defensible miss-rate and uncertainty only after recorded decisions.
- [ ] Update #182's **recruitment scope** from 511 to the reviewed v2 count only after the pinned replay and the named domain-owner decision. Keep the 33 account PHP candidates first, add the high-risk security claim and lifecycle/expiry keys irrespective of raw counts.
- [ ] Do not alter PR #183's zero-diff catalogue migration, PR #184's locale behaviour or the pinned PR #180 baseline. This is **candidate discovery**, not a reviewed translation release.

