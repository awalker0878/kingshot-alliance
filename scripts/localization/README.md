# Translation delivery

The English catalogues are authoritative. The application supports English and 16 translated locales. Existing non-English translations must be retained when filling missing entries.

## Durable continuation state

- Branch: `codex/audit-stabilization`; draft PR: [179](https://github.com/awalker0878/kingshot-alliance/pull/179).
- Server-language checkpoint: `bac0a9a1bbf399c0213b4e0ee6572b18a70882ae`.
- That checkpoint passed [CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/40682049852) and [browser regression](https://github.com/awalker0878/kingshot-alliance/actions/runs/40682049761).
- Saved generation batch: [40730353999](https://github.com/awalker0878/kingshot-alliance/actions/runs/40730353999); immutable pre-translation baseline `910544783e5c6897ea26a7aa239dcde8f0789cc1`.
- All 16 draft jobs completed successfully. All locale catalogues, English-key server messages and account email catalogues have now been filled. Targeted review repairs terminology, omitted placeholders, English retention and identified semantic errors. Full native-speaker editorial review remains pending.
- October 29 repair: corrected validation text in Arabic, German, Japanese, Korean, Turkish and Vietnamese. Required field/value placeholders are retained; Japanese missing-field and Korean declined-condition wording now matches the rule.
- Framework catalogue verification now runs separately for each locale/catalogue. It accepts Laravel-supported capitalization and repeated parameters, then renders every message through the locked Translator or Validator with English fallback disabled. Unsupported case variants and unexpanded rule parameters still fail. The new checks fail against the original six defective catalogues and pass against the repaired files.
- Validation-repair checkpoint `6aace296d62220d5ce013cc7a3bce21f4d28bb9a` passed [full CI](https://github.com/awalker0878/kingshot-alliance/actions/runs/40856799811): 2,175 PHP tests / 121,807 assertions, frontend checks, production image build, ephemeral staging and backup/restore. Its [browser regression](https://github.com/awalker0878/kingshot-alliance/actions/runs/40856799875) passed 110 tests with two artwork-dependent cases skipped.
- The subsequent wording pass corrects 832 UI entries, including 370 account/sign-in/recovery entries across all 16 translated locales. Recovery copy preserves account-enumeration privacy, confirmation supports linked sign-in methods, and onboarding explains the selected Governor's authority. Governor release controls preserve the distinction between unlinking ownership and deleting the game identity; notification defaults and per-Governor settings also receive targeted corrections. Corrections are saved in `reviewed-keys.json` so recompilation retains them.
- The continuation recovers 30 earlier UI corrections plus nine server messages and one account-email label that had not reached the branch. These repair reversed success/availability messages, eligibility and share-expiry wording, Thai finite-number and locking instructions, and Vietnamese recovery copy. Their source-level corrections are retained in `reviewed.json`.
- The completeness gate now normalizes capitalization, whitespace, Unicode character width, punctuation, symbols and invisible formatting characters before detecting English retention. Across base and extension-provided keys, 169 capitalization-only matches included 155 missed translations and 14 legitimate French/German shared terms. A second scan found 24 punctuation-only matches: 20 labels are translated, two event names use their canonical English spelling, and two labels are valid French. Shared terms and proper names remain explicit locale-specific exceptions.
- Local validation of the wording continuation: the full `npm run check` passes, including all 204 source tests, 90,256 UI entries across 16 translated locales, 1,112 server messages per translated locale, build and unchanged performance limits. The 75 PHP catalogue/request/mail cases pass with 22,077 assertions; PHP formatting also passes. Hosted CI and browser results for each published candidate are tracked in the draft PR.
- Known independent limitation: the strict map-art gate still awaits 24 source-art families.

## Generate drafts

`node scripts/localization/export-input.mjs OUTPUT_DIRECTORY` reads the actual Vite catalogue loader, including extensions, and collects English-key server messages and account email text. It exports the effective catalogues separately so catalogue compilation can retain existing translations.

The **Translation drafts** workflow saves both that input and one checkpoint artifact per locale for 30 days. On this draft branch, a head commit whose subject contains `[translation-drafts]` explicitly requests generation. Other updates skip the batch. Manual workflow dispatch is also supported once the workflow is on the default branch. The workflow has read-only repository permissions and does not commit or deploy output.

The generator uses a pinned, checksum-verified CTranslate2 conversion of [Google MADLAD-400](https://huggingface.co/google/madlad400-3b-mt), published by [Nextcloud](https://huggingface.co/Nextcloud-AI/madlad400-3b-mt-ct2-int8/tree/aa32bbdeba7880eff2096ec044cb155a340a9400). Model weights are Apache 2.0 and are not shipped with the application. Tools are isolated from application dependencies. Shared software phrases use the pinned Laravel Lang translation data under the [existing MIT notice](../../lang/NOTICE.md).

`resources/data/localization/reviewed.json` records terminology corrections as English source text mapped to locale/value pairs. These override generated drafts, without replacing existing localized catalogue values. `reviewed-keys.json` stores targeted corrections by domain/key and locale; these take precedence over preserved catalogue values as well as generated drafts. The output records distinguish existing translations, shared software translations, reviewed terminology, invariants and machine-assisted drafts.

A completed generation job is **not linguistic sign-off**. Review technical/game terms and account-security wording; repair missing or changed placeholders, numbers, inline identifiers, accidental English retention and truncated output. Sentence boundaries are preserved to prevent translation models dropping an earlier sentence. Do not compile a null or empty result as English fallback.

## Recovery

Recover the latest Translation drafts run's `translation-input-*` and `translation-draft-<locale>-*` artifacts before rerunning a locale. Each JSONL record includes its exact English source; each report includes the input SHA-256 and model revision. A partial output can be resumed with the same input and output path. Never substitute output from the rejected M2M100 experiment or from a different source inventory.

## Compile without overwriting existing translations

Export the immutable baseline before compiling or recompiling: `node scripts/localization/export-input.mjs .translation-baseline-input 910544783e5c6897ea26a7aa239dcde8f0789cc1`. This records both raw catalogues and effective catalogues after overlays. Retain raw localized values hidden by an English overlay only when the English source has not changed. Never use already-generated catalogues as the preservation baseline.

Compile with `node scripts/localization/compile-catalogues.mjs .translation-baseline-input .translation-output`. Optional fourth argument selects one locale. Compilation checks every entry before writing any files, and saves a diagnostic report beside the drafts. Run `npm run check:localization-completeness` to inspect raw catalogues, runtime overlays, English-key server messages and parameter parity without English fallback. Run `php vendor/bin/phpunit tests/Shared/Infrastructure/Http/Unit/LanguageCatalogueTest.php` to verify account and framework message keys, parameter identities and actual runtime interpolation in every supported language. Locale-specific unchanged words are explicitly listed in `unchanged.json`; never add an untranslated sentence to that list.
