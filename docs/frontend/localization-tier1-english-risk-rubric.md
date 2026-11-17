# Tier 1 English-source blind classification rubric — version 1

**Decision standard, frozen before human scoring.** Apply the rubric to **the intended effect of the English source text in its workflow**, not how a foreign-language translation reads. One qualified English-language reviewer can screen 492 distinct English messages before native-language translation review. An accountable account/security/product owner must adjudicate ambiguous high-risk cases. Neither the reviewer nor the scorer should see which examples the classifier flagged, or the Spanish/Indonesian sampling labels, until decisions are locked.

## Tier decision

Select exactly **`tier1`**, **`not_tier1`**, or **`needs_context`**. `tier1` means an incorrect translation or misleading wording could materially change whether a user can safely perform an action, preserve access/credentials/data, understand an entitlement or irreversible effect, or act before a material deadline. Do not assume a key containing “confirm” or “pending” is automatically high risk; context and the user's likely decision matter. `needs_context` is unresolved, **not** a negative or a pass.

## Risk categories and anchored examples

| Category | Examples ordinarily Tier 1 | Borderline examples requiring context | Usually not Tier 1 |
|---|---|---|---|
| **Security, identity and access** | “This code will reset two-factor authentication”; “All sessions will be revoked”; “Secret credentials are encrypted and never returned” | “Confirm it's you” on a security challenge versus an ornamental welcome dialog | “Security” as a navigation heading; “Account overview” |
| **Irreversible, destructive or permissioned action** | “Delete this account permanently”; “Transfer cannot be undone”; “Only the owner may revoke access” | “Apply changes” when the change may involve deletion versus a harmless filter toggle | “Open details”; “Sort list” |
| **State and consent/confirmation** | “Queued” versus “Sending” when the state drives retries or account notifications; “Approve up to 50 changes”; “Blocked or already current” before a bulk action | “Pending” in an informative banner with no required user action; “Confirm” for merely closing an informational pop-up | “View all”; “Notification history” |
| **Time, quantity, eligibility and money** | “Expires in 30 minutes”; “Transfer closes at midnight”; “Minimum 50 units required”; “You are not eligible to claim” | “Expiring” on a non-actionable gift-code badge; “In progress” on a decorative dashboard counter | “Last updated”; “About this event” |

These examples **define how to assess the risk**; they are not prescored sample outcomes. Any contradiction between a state key and a displayed phrase, or any security assertion without confirmed product behaviour, should be `needs_context` until the responsible owner resolves it.

## Blind, randomized workflow

1. Regenerate the queue with `node scripts/prepare-localization-baseline.mjs .translation-pr1-baseline` and `node scripts/build-localization-tier1-blind-review.mjs .translation-pr1-baseline/risk-unflagged-samples.json .translation-pr1-baseline`. CI confirms the source SHA-256 and exactly **512 locale placements → 492 unique English source keys**.
2. Use `english-source-blind-queue.csv` in its **seeded shuffled order**. It exposes source key and English message, **not** sampling locale, translated text, old/new classifier membership, keyword matches, or risk flags. Don't reorder items to prioritize “Queued,” “Confirm it's you,” or any known hits.
3. Reviewer fills `tierDecision`, `riskCategories`, rationale, reviewer ID/date and optional `needsContext`; ask the product owner for UI/action context where essential, but do not show algorithm predictions.
4. **Lock the original decisions** before deblinding and owner adjudication. Report reviewed, unresolved and adjudicated counts; backfill each reviewed English judgment to its sampled locale placements (no native-translation claim).
5. Compare accepted human Tier 1 labels with v1 and v2 classifier membership only **after** decisions are frozen. Use an appropriate definition of “missed among this previously unflagged sample” and distinguish it from the conventional false-negative rate `FN/(FN+TP)`, which cannot be estimated from a sample containing only original negatives. Provide a confidence interval and avoid double-counting repeated English keys. A holdout used for tuning ceases to be independent validation.

**Scope controls:** No translation strings or pinned baseline fingerprints are changed by this review. English-source risk triage cannot certify accuracy or fluency in any locale. No reviewers or review slots have been assigned.

## Independent second reviewer — 50 blind source keys

Generate and assign `english-source-second-review-blind-50.csv` to a **different** qualified English-speaking reviewer, independently of the main 492-key reviewer. The 50 items are deterministically sampled using a separately frozen SHA-256 seed and shuffled independently; their `reviewId` and `sourceId` support later pairing. The second reviewer must not see the first reviewer's judgments, classifier flags, translation strings or later adjudications before recording their own `tier1` / `not_tier1` / `needs_context` decisions.

Once both sets are signed and locked, measure **exact agreement = matching decisions / 50**, and a chance-corrected Cohen's kappa using the three categories. Report the three-by-three disagreement table, counts of `needs_context`, and category-specific disagreement rates; adjudicate conflicts separately and do not erase the original independent labels. A raw 20% disagreement rate is evidence of review uncertainty, not automatically 20% classifier error. This 50-item estimate has sampling uncertainty and does not replace representative positive-class testing or qualified native translation QA. Neither reviewer has been assigned.
