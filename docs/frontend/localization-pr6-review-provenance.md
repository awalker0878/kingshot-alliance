# Native review integrity and release coverage

Two distinct CI checks now run on every PR against the audit target and on pushes to that target. `Verify recorded native approvals` checks the integrity of claims that exist. `Require approved native review coverage` separately requires a nonempty, independently approved scope and a verified native approval for every required message/locale pair. Empty records may pass integrity while the release coverage check fails with `REVIEW_REQUIRED`.

There are currently zero reviewer assignments, zero scope approvers and zero native approval records. The release scope is explicitly unapproved. No classifier result, AI output or empty directory constitutes linguistic approval.

## Native records and stable identities

Each JSON record in `resources/data/localization/reviews` has `version: 2`, `decision: approved` and `scope: native-translation`. It binds catalogue, domain, key, locale, exact source/translation SHA-256, reviewer GitHub login, reviewed PR, reviewed commit and review ID. The filename is SHA-256 of `catalogue:domain:key:locale` with a `.json` suffix; `binding()` in the provenance checker computes `reviewBindingSha256`.

PHP keys use Laravel group/key identities such as `accounts.mail.verify.subject`. Positional identities such as `accounts.php#1` are rejected. The PHP exporter evaluates trusted catalogue arrays with PHP itself, preserving escaping and nested keys. Current exports can include locked Laravel English framework catalogues with recorded file and Composer-lock hashes. Missing source or translation text cannot be approved. The separate immutable ordinal bridge exists only to recover the historical candidate identity; it is never a new approval reference.

A qualified native reviewer must submit an actual GitHub **Approve** review containing an exact line:

```
Approval-Record-SHA256: <actual reviewBindingSha256>
```

The checker verifies the authenticated review ID, `APPROVED` state, login, digest, submitted timestamp and live PR head. The record commit, review commit and current head of that reviewed PR must agree. Self-approval, dismissed reviews, wrong IDs, changed text and stale heads fail. Repeated records sharing an approval reuse one authenticated API response within the run.

The reviewer must also have an active, qualified assignment for the exact locale, catalogue and domain in `reviewer-assignments.json`. The locale list derives from the shared registry. CODEOWNERS custody does not imply native proficiency.

## Approved release scope

`release-review-scope.json` starts with `status: review_required` and no units. After independent English-source classification and adjudication, the product/security owner selects the required message/locale units. Every unit binds a stable identity and exact current English and translated hashes. The manifest also binds the source revision, classifier revision, locale registry and a description of the approved scope.

A scope approver must be explicitly appointed in `scope-approvers.json` with active status and source-risk qualification. `scopeBinding()` hashes the complete contract with deterministically sorted units. A real, independent GitHub Approve review on the frozen source PR must contain:

```
Localization-Scope-SHA256: <actual scopeBinding(scope)>
```

Its PR, review ID, login and current head are checked just as strictly as native evidence. Changing units, hashes, classification rules or the locale registry invalidates the scope binding. An empty approved scope is rejected. The original 511 candidates and exploratory 613 are not automatically converted into mandatory release scope.

Coverage then resolves every unit against current exports and revalidates each native approval. Duplicate units, duplicate records, unsupported locales, missing approvals, stale content, unassigned people and unverifiable GitHub evidence fail. The success result means the independently selected scope is covered; it does not claim every string in every language has been reviewed.

## Operational review round-trip

1. Appoint actual qualified source-risk owners and native reviewers, obtain their consent/access, and protect the governing assignments and scope files through the repository review process.
2. Freeze the exact source PR head and blinded classification artifacts from #185. Lock independent English judgments before deblinding, adjudicate disagreements and select the release scope.
3. Export current values, prepare the source/translation hashes and proposed bindings, then let the independent people review those exact values and submit their GitHub Approve attestations.
4. Record real PR/review IDs and digests in a **separate evidence PR**. Keep the reviewed source PR head unchanged; adding records to that same PR after approval would correctly invalidate its head binding.
5. Run both checks with read-only authenticated GitHub access. Inspect the retained `review-integrity.json` and `release-coverage.json`, including missing units. Verify a real native reviewer round-trip before treating the process as operational.

Tests use explicit verification fixtures to cover tampering and missing coverage. They are not evidence that a human review round-trip has occurred. No real reviewer has yet completed that acceptance step.

## Governance status and validation

On November 13, 2026, GitHub's branch listing reports `codex/audit-stabilization` at `126ef527cd6f14bb265fdc520551304b6a57c161` with `protected: false`, and the repository ruleset listing is empty. These are observed settings, not an enforced review policy. The maintainer must require code-owner reviews, stale-approval dismissal and the appropriate status checks through repository administration before release acceptance. Adding CODEOWNERS text alone does not enforce them.

The production release checker is tested against the real empty bootstrap: integrity reports zero approvals and release coverage exits unsuccessfully with `REVIEW_REQUIRED`. Eleven focused tests cover valid fixture coverage, empty/unapproved scope, missing units, forged/dismissed approval, stale rules/registry/content, duplicate identities, wrong review IDs, assignments and stable PHP resolution. Hosted final-head validation and a real independent review remain required.

The branch includes prior runtime/registry prerequisites and #185's stable-key infrastructure. This governance slice changes no message value and implements no preference synchronization. It remains draft until the external review and administration requirements are fulfilled.
