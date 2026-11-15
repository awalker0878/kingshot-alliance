# Localization implementation and acceptance

## Resulting structure

The server and browser now share technical locale definitions and maintenance contracts while retaining their native message formats. Laravel's `lang/` directory stays at the repository root; moving its PHP files into browser resources would not make Vue consume them or put them in the production PHP image.

| Location | Responsibility |
| --- | --- |
| `resources/data/localization/locales.json` | Authored codes, names, directions, aliases and default |
| `app/Shared/Infrastructure/Http/LocaleDefinitions.php` | Generated server definitions |
| `resources/js/localization/locale-definitions.generated.ts` | Generated browser definitions |
| `lang/<locale>.json` and `lang/<locale>/*.php` | Laravel validation, server responses and account mail |
| `resources/js/localization/messages/<domain>/<locale>.ts` | Canonical lazy-loaded Vue messages, with former overlays consolidated |
| `resources/js/localization/async-coordinator.ts` | Locale and page generations, accepted-page boundaries and failed-import recovery |
| `resources/js/localization/preferences.ts` | Versioned same-origin preference ordering and synchronization |
| `scripts/localization/` | Registry generation, draft compilation and stable PHP review identities |
| `resources/data/localization/reviews/` | Real, hash-bound native-review evidence; currently no approvals |
| `resources/data/localization/release-review-scope.json` | Independently approved release scope; currently unapproved |

The compiler keeps key corrections, preserved contextual text and source-reviewed text ahead of saved machine drafts. Replaying all 16 draft languages against the immutable source must produce no catalogue diff. Generated definitions are checked for staleness before the frontend checks.

## Changes combined for acceptance

This acceptance branch combines the existing reviewable work without merging the audit target or deploying the application. The exact current head and CI links belong in the acceptance pull request so recording a test result does not itself change the tested source revision.

| Work | Pull request | Component head before combined acceptance | Observed component evidence |
| --- | --- | --- | --- |
| Runtime catalogue packaging and worker probe | [#187](https://github.com/awalker0878/kingshot-alliance/pull/187) | `9c4baf27ef3d1d256e2acde132839317a940ac1f` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43258257051), [browser](https://github.com/awalker0878/kingshot-alliance/actions/runs/43258257091) passed |
| Async navigation, retry and stale DOM prevention | [#181](https://github.com/awalker0878/kingshot-alliance/pull/181) | `0547cd8afa80c7f89a563f491d77ade84357681b` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431118666), [browser](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431118648) passed |
| Strict normalization and raw HTTP negotiation | [#184](https://github.com/awalker0878/kingshot-alliance/pull/184) | `f3901f7ed428ff7c8d6a026af688bf0349a9660c` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431125893), [compatibility](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431125647) passed |
| Remaining overlay consolidation | [#189](https://github.com/awalker0878/kingshot-alliance/pull/189) | `9e8199b1082191a08fc0ebe50ed2f241e076d661` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431139268), [parity](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431139182) passed |
| Shared registry and compiler precedence | [#190](https://github.com/awalker0878/kingshot-alliance/pull/190) | `83791f6b40b060fbe638bbc30f0082b5bd78ee63` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431139813), [replay](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431139910) passed |
| Stable source-risk identities | [#185](https://github.com/awalker0878/kingshot-alliance/pull/185) | `6ac6a79e303da44ad65d8ace8059a0a92535ccd9` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431532125), [pinned risk replay](https://github.com/awalker0878/kingshot-alliance/actions/runs/43431531510) passed |
| Native-review integrity and separate release gate | [#186](https://github.com/awalker0878/kingshot-alliance/pull/186) | `726b4bb90903d2e75f8358c3fc0f4b0727fa6b6f` | [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/43432711024) passed; [provenance passed, release coverage failed](https://github.com/awalker0878/kingshot-alliance/actions/runs/43432710809) because scope is unapproved |
| Cross-tab preference synchronization | [#191](https://github.com/awalker0878/kingshot-alliance/pull/191) | See the PR's final tested head | Local checks passed; hosted results must be assessed at the final head |

These are historical component results observed on November 15, 2026, not substitutes for testing the combined tree. The new run discovered [GHSA-63hm-g96r-2m2q](https://github.com/laravel/framework/security/advisories/GHSA-63hm-g96r-2m2q). Combined acceptance uses Laravel 13.40.0 and the required `laravel/prompts` 0.3.25, preserving other locked package versions. Prior passing dependency scans predate that advisory's database publication and must not be treated as current security clearance.

## Required combined evidence

- Run the complete frontend check, PHP formatting/types/regression, dependency scans, container scan and ephemeral staging/recovery workflow at the exact candidate head. The final image is identified by digest and release SHA; app, web, worker and scheduler must use that same image.
- Run the real runtime-image locale/mail probe without repository bind mounts. Its missing-`lang` negative control must fail. Serialized French and Arabic queued notifications must render under an English worker and restore its locale afterward.
- Run desktop/mobile navigation and cross-tab tests against built chunks, plus representative English, French, Brazilian Portuguese and Arabic/RTL layouts. Inspect browser skips explicitly: the existing two T15 cases are not passed artwork acceptance.
- Run the pinned classifier/stable-identity replay, review-integrity forgery/staleness tests and actual release-coverage command. Zero human evidence is a release failure even when the integrity job correctly accepts an empty record directory.
- Run all 16 language compiler replays, the macOS pinned export, six effective/server/risk fingerprints, structural raw-source allowlist and their mutation controls. The acceptance branch enables the existing workflows for this purpose.
- Preserve measured lazy-loading boundaries and performance budgets: initial JavaScript at most 225 KiB and any page at most 72 KiB. No budget increase or fixed expected historical size delta substitutes for measuring the candidate build.

The six unchanged message/risk fingerprints are:

| Export | SHA-256 |
| --- | --- |
| Effective messages, all locales | `2ab071d65edd083aadd2f3707dffcf2f1490f38ded8aa87bb8a59cf401f2f834` |
| Effective English | `ea395ece0aec11d81ba0bd5560e244582586aa57c87ac0bda90cd5e7eab7ff7c` |
| Server JSON | `fd7f76496e863745b1a182c8a7f91e9278d630375f227df4b218facc8a75ba00` |
| Server PHP values | `f52b7364ea834f277fa8368b3da7c1be2702e38ee00e480e993fccb83652601a` |
| Server PHP source hashes | `5b378320685802ed10ccbfa5222adacd28cac9039d006584a1259a35ca33aaf7` |
| Semantic risk inventory | `4bb3cf12050926364bd7e85b630bf5f6742f1ae6608e47f90ee2349ee16054e0` |

## Outstanding release decisions and external evidence

Engineering checks do not complete these requirements:

1. An independent qualified reviewer must adjudicate/freeze the source-risk scope. The exploratory 613-key screen is not an approved release scope. Qualified native reviewers must be appointed and their actual GitHub approvals recorded against exact source/translation hashes; the authenticated round trip must be demonstrated with real evidence. No human approvals have been fabricated.
2. A repository administrator must enforce the required checks, code-owner review and stale-approval dismissal. On November 13 the audit branch reported `protected: false` and repository rulesets were empty. Recheck settings before merge; CODEOWNERS alone does not enforce them.
3. The approved strict HTTP/whitespace policy still needs the independent HTTP review identified in the plan.
4. The [existing KingdomMaps artwork gate](https://github.com/awalker0878/kingshot-alliance/actions/runs/43432710860) reports 24 missing source representations in the rights-cleared master pack. Resolve that through its own artwork acceptance work; neither localization nor a skipped browser case clears it.
5. Ephemeral CI staging is not deployed Azure acceptance. After an authorized deployment, record the app/worker release SHA and image digest, then exercise actual validation/account-email delivery through the deployed queue/outbox. No deployed journey is claimed here.

## Merge and rollback procedure

Keep the component PRs for focused review. Either merge them in dependency order or review the combined acceptance candidate, but verify expected head and tested tree immediately before the chosen merge. R1 precedes R2, R3, R4 and R5; R6 and R7 build on R5. Do not merge both paths blindly or reuse obsolete component checks after a dependency change.

After merge, verify the target SHA and run the post-merge fingerprint workflow on that actual target. A successful candidate PR is not post-merge evidence. Retain CI URLs, browser counts/skips, artifact digests and any approved release-scope record alongside the reviewed candidate.

Deploy only a verified image and retain the last verified release digest. If staging fails, restore that image. A code revert of the packaging fix restores the known missing-catalogue defect and is not a healthy release fallback. Preference rollback must include its PHP parser/cookie exception; legacy mirrors preserve the last confirmed language. Registry/generated-file and compiler changes revert together. Preserve immutable baseline and human-review records throughout rollback; changed source hashes invalidate approvals rather than silently transferring them.
