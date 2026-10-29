# Complete language catalogues

The English source catalogues cover 5,641 UI entries across 19 domains. All 16 other supported locales contain those entries directly: Arabic, German, Spanish, French, Indonesian, Italian, Japanese, Korean, Polish, Brazilian Portuguese, Russian, Thai, Turkish, Vietnamese, Simplified Chinese and Traditional Chinese.

The delivery fills 66,929 missing UI entries, starting from 23,327 existing localized entries and preserving them except for explicit corrections. It also adds 1,112 English-key server messages per language and translates account email and security notifications. Framework authentication, password-reset and validation translations retain their upstream attribution in [the language notice](../../lang/NOTICE.md).

## Runtime behavior

Language selection persists in an allowlisted cookie used by server validation and account notifications. Browser language is the fallback when no valid explicit preference exists. Queued verification and password-reset messages retain the language captured when requested. Arabic pages and account email use right-to-left direction. The preference is currently browser-specific; cross-device account preference persistence remains open.

Translated domain files include their complete extension surface. English remains composed from its base catalogue and extensions; English extensions no longer overwrite non-English values. The existing lazy domain/locale chunks are retained.

## Validation

`npm run check:localization-completeness` checks all 90,256 non-English UI entries without English fallback, plus every server message in each locale. It rejects missing values, parameter changes, English copies disguised by cosmetic formatting, runtime overlay replacements, and numeric additions to labels without numeric source meaning. Explicit locale-specific exceptions cover brands, technical examples and legitimate shared words or labels.

The page-contract check verifies interpolation and domain resolution across 93 pages and their shared dependencies. Event and Territory checks compare against the effective English catalogue, including extensions. PHP catalogue tests run with English fallback disabled and exercise the actual Laravel Translator and Validator. Request and mail tests cover language selection and account-email rendering in all 17 supported languages. Browser tests exercise English, French, Brazilian Portuguese and Arabic layouts.

## Translation review and maintenance

Draft generation used a pinned, checksum-verified MADLAD-400 translation model. Source phrases, model provenance and recovery instructions are described in [the translation workflow](../../scripts/localization/README.md). No model weights or translation service dependency ship with the app.

`reviewed.json` records 2,379 source/locale corrections across 764 English phrases. `reviewed-keys.json` retains corrections that depend on context, including account recovery, Governor ownership release, action versus status labels, and messages whose generated wording reversed success or availability. Key-specific corrections take precedence when recompiling.

These are machine-assisted translations with targeted review. Complete native-speaker editorial review remains pending. Mechanical completeness does not establish fluency or official in-game terminology. Review should prioritize account-security copy, event instructions and transfer eligibility explanations. Interface translation also does not establish multilingual free-text understanding in the Alliance Assistant.

For reproducible recompilation, export the immutable pre-translation revision recorded in the workflow documentation. Do not use generated catalogues as the preservation baseline. New English text must be translated into every locale before the completeness check will pass.
