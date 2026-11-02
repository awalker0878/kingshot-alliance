# Localization stabilization — PR 1 baseline and critical corrections

**Starting point:** codex/audit-stabilization at 7cf387b628bbbaeb81d10120340e559b46403bee.
**Scope:** baseline exporter, early semantic-risk classification, critical glossary, confirmed server-language fixes, and regression tests.
**Out of scope:** catalogue consolidation, locale preference changes, API changes, account-level persistence, review approval claims and game logic.

## Run and retain the baseline

After installing locked dependencies:

    npm ci --no-audit --no-fund
    npm run build
    npm run localization:baseline
    npm run check:localization-semantics
    npm run check

The default exporter archives source from the **immutable commit 7cf387b628bbbaeb81d10120340e559b46403bee**, not `HEAD` or GitHub's synthetic PR merge commit. It writes untracked artifacts under `.translation-pr1-baseline/`; CI exports twice and requires byte-identical outputs. The `--current` mode separately captures the corrected PR 1 working tree for PR 3's zero-diff acceptance test, also checked by two exports. Retain both as CI/PR evidence. Do not commit the large derived catalogues. CI artifacts expire after 14 days; later jobs should regenerate using the pinned commit and verified script, and retain the final corrected source commit SHA from this PR.

Exports include raw and effective client catalogues, effective English catalogues, per-locale server JSON, PHP language file hashes, overlay-added or overridden English values, cross-domain duplicate keys, deterministic risk samples, and build chunk inventory. The overlay inventory captures effective winning values; complete intermediate overlay-module attribution is part of PR 3. Pin the **approved PR 1 commit after critical corrections** as the baseline for subsequent structural PRs rather than comparing to a moving branch.

Candidate Tier 1 counts are reported for all 16 translated locales. The exporter selects 32 deterministic non-flagged messages **per locale**, seeded with the immutable commit SHA. `risk-sample-metrics.json` reports `sampled`, `reviewed`, `missedTier1`, and `estimatedMissRate` per locale. An unreviewed sample reports a **null** miss rate, not a fabricated zero. Human reviewers record decisions in `risk-sample-decisions.json`. Any discovered critical omission marked Tier 1 but not triaged fails the exporter loudly. Keyword classification is candidate discovery, not an exhaustive risk audit. The account PHP inventory identifies source values by index; stable PHP group/key metadata and source review for the locked Laravel framework catalogues are follow-up scope for PR 2.

## Confirmed language fixes

Four Arabic server JSON messages that reversed numerical constraints or misidentified administrative concepts are corrected in lang/ar.json, preserved in resources/data/localization/reviewed.json, and regression-tested using resources/data/localization/semantic-regressions.json. These are focused corrections, not approval of the whole Arabic catalogue. Every fixture has `reviewStatus: review_required` and `reviewer: null`; no native-speaker sign-off is claimed. A dedicated unit test restores each original bad value individually in a temporary catalogue and asserts that the production semantic checker fails.

The initial glossary records precise English meanings. Native-language approval and reviewer assignment remain unresolved.

## Translation regeneration replay

The optional CI workflow `localization-pr1-regeneration.yml` retrieves the original 16 saved draft artifacts from run 40730353999, reconstructs pre-translation input from 910544783e5c6897ea26a7aa239dcde8f0789cc1, runs `compile-catalogues.mjs`, formats generated TypeScript, and fails on any diff from the corrected files. This proves recompilation from the saved drafts; it does **not** rerun the machine-translation model. Because those artifacts expire, retain a separate longer-term archive of the drafts or explicitly re-run the pinned generator before expiration if indefinite regeneration is required.

## Downstream decisions: multi-tab preferences (PR 5B)

PR 5B depends on both PR 4 normalization and PR 5A's generation-protected locale-selection commit.

A pending selection records the cookie value **last observed when the selection committed**. On reconciliation, if the cookie now differs from that observation, an intervening tab or server-side change may have advanced the preference. Discard stale pending intent and adopt the newer valid cookie. If unchanged, retry writing the pending selection. If unavailable, retain the frontend selection and pending local preference where possible, clearly noting server-language inconsistency.

| Pending selection | Last observed cookie | Current cookie | Decision |
|---|---|---|---|
| Arabic | French | French | Retry Arabic write |
| Arabic | French | German | Discard stale Arabic, adopt German |
| Arabic | Missing | French | Discard stale Arabic, adopt French |
| Arabic | French | Unavailable | Retain frontend intent; report unsynchronized server |

Add browser storage event handling for local preference and pending-intent keys. Re-read the cookie and use the PR 5A generation guard before committing any event-driven selection. Test two-tab Arabic/French races and blocked cookies. The storage event does not fire in the tab making the change; local commits must update that tab directly. Timestamps alone do not establish ordering across tabs, and cookie observation checks are not an atomic inter-tab transaction.

## Downstream decisions: provenance (PR 6)

Provenance records cover **both runtimes**: catalogue=client with a domain/key, and catalogue=server with domain=json and an English-key string or domain=php with a stable PHP group/key. English source and translated value hashes bind review status to content.

PR 6 must use **post-consolidation effective values from PR 3** after PR 3's zero-diff gate. Moving files without changing effective values must not invalidate review. Review records are committed under resources/data/localization/reviews/<locale>.json.

PR 6 can merge the schema, metadata validation and CI machinery with every Tier 1 item still review_required. This is an expected bootstrap state until qualified reviewers are assigned and CODEOWNERS plus branch protection are enforced. Merging PR 6 does **not** mean native-language review is complete.

## Performance, effort, QA

The existing checker measures uncompressed Vite output. The reported successful candidate measured 214 KiB initial JavaScript against 225 KiB and 71 KiB largest page chunk against 72 KiB. The largest page is Operations/Events/Manage.vue, not a locale file. PR 3 must compare exact chunk byte sizes and verify hard limits without arbitrary +1 KiB growth restrictions.

Engineering estimates for the planned PRs total 17–29 engineer-days excluding review and QA. Reserve an initial **4–7 tester-day QA allowance** for browser, PHP request and email, RTL, and multi-tab cases; revise after classification and test planning. Reviewer-hours are TBD until the Tier 1 counts and sample-review workload are known.

## Regression gates remain additive

Existing check scripts remain in npm run check: check:page-localization-coverage, check:event-localization-coverage, check:territory-localization, check:product-language, check:localization-contracts, check:localization-completeness, check:localization-chunks, and check:performance-budgets. The new check:localization-semantics is **added**, not substituted. Retain backend PHP catalogue/request/mail and representative browser tests.

## PR 1 completion

- [x] Correct confirmed Arabic server-language semantic defects.
- [x] Preserve corrections for regeneration.
- [x] Add regression fixtures and semantic check.
- [x] Add a critical source-language glossary.
- [x] Add raw/effective catalogue and provisional Tier 1 inventory exporter.
- [x] Add deterministic non-flagged per-locale sampling.
- [ ] Execute the production build and publish baseline artifact counts.
- [ ] Verify full frontend, backend, and browser CI on the PR.
- [ ] Manually review classification omissions and reviewer assignments before approving translations.
