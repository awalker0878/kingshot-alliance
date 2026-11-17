# Shared locale definitions and compilation contracts

`resources/data/localization/locales.json` is the authored technical registry. It declares 17 supported codes, native and English display names, text direction, the default, and 22 aliases with explicit descendant matching. `npm run localization:generate` produces self-contained PHP and TypeScript definitions. `npm run check:locale-definitions`, also part of `npm run check`, rejects missing or stale outputs. The production PHP image does not need to read the source JSON at runtime.

| Path                                                        | Responsibility                                        |
| ----------------------------------------------------------- | ----------------------------------------------------- |
| `lang/<locale>.json`, `lang/<locale>/*.php`                 | Laravel messages and account mail                     |
| `resources/js/localization/messages/<domain>/<locale>.ts`   | Vue domain messages                                   |
| `resources/data/localization/locales.json`                  | Authored locale definitions and alias policy          |
| `resources/js/localization/locale-definitions.generated.ts` | Generated browser definitions                         |
| `app/Shared/Infrastructure/Http/LocaleDefinitions.php`      | Generated server definitions                          |
| `locales.ts`, `SupportedLocale.php`                         | Existing runtime interfaces and normalization grammar |
| `scripts/localization/`                                     | Generation, drafting and compilation contracts        |

The app document and account mail use the generated direction through `SupportedLocale`. The translation draft matrix, Python CLI choices and regeneration locale list read the same registry. Historical review inventories remain bound to their original source revision; adding a current locale must not silently alter a frozen review round.

`SupportedAllianceLocale` remains an Alliance-owned enum. A contract test verifies its currently deliberate equality to interface locale codes. A new interface locale therefore requires a separate decision about alliance content language support. Event timezones are unaffected.

## Normalization compatibility

Aliases are lowercase matching tags, not additional catalogue paths. Descendants match only after the existing strict standalone grammar, length and whitespace checks. The generator rejects duplicate codes, unknown defaults, duplicate aliases and overlapping prefix rules.

- `pt` and `pt-BR` map to `pt-BR`; valid descendants of `pt-BR` also match. `pt-PT` remains unsupported.
- `zh`, `zh-CN` and `zh-Hans` map to `zh-CN`; `zh-TW`, `zh-Hant` and `zh-HK` map to `zh-TW`. Bare `zh` does not accept arbitrary descendants such as `zh-SG`.
- Other supported language codes accept valid regional/script descendants by their base language.
- Standalone trimming still permits only boundary space, HTAB, LF, CR and vertical tab after rejecting NUL. Raw HTTP negotiation retains its separate SP/HTAB grammar and quality-weight policy.

The existing shared fixture and HTTP/browser compatibility reports remain authoritative. The contract extraction adds no normalization changes to the already proposed whitespace fix. Independent HTTP review remains required for that prerequisite.

## Compilation precedence

Recompilation reads the immutable pre-translation input at `910544783e5c6897ea26a7aa239dcde8f0789cc1`, including historical raw and effective values. Generated output must never become its own preservation baseline.

| Catalogue                   | First available value wins                                                                                                           |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Vue UI                      | `reviewed-keys.json` domain/key correction → preserved contextual translation → `reviewed.json` source-text correction → saved draft |
| Laravel JSON / account mail | `reviewed.json` source-text correction → saved draft                                                                                 |

Preservation first uses a non-English effective value. An English overlay may reveal a raw prior translation only if the English source is unchanged (or no previous source existed). A changed source must not recover a stale raw translation. A broad terminology correction cannot replace an existing contextual UI translation; use a domain/key correction for that purpose.

Every selected value, including explicit corrections, passes the same completeness, placeholder and numeric checks. Any failure aborts the entire compilation before application files are written. The real compiler integration tests exercise competing values at every level, changed-source recovery, server/mail precedence, and an invalid placeholder correction that leaves all application files untouched.

No correction source is retired. The regeneration workflow reuses the original saved drafts from run `40730353999`, rebuilds immutable input, compiles all 16 translated locales and requires a zero file diff after formatting. Missing archived drafts fail this check; newly generated drafts are not equivalent evidence.

## Validation and release boundary

The local replay of all 16 original drafts produces no catalogue or `lang/` diff. The 73-case standalone compatibility matrix, nine preference-priority examples, raw HTTP fixtures and eight realistic browser examples still agree. Generated definitions have deterministic-output and stale-file negative controls; alias ambiguity is rejected. PHP registry/catalogue/mail tests and the existing source, semantic, lazy-loading and performance checks remain required.

This contract extraction builds on the canonical catalogue move in #189. It does not merge Laravel and Vue message ownership, approve native wording, deploy a release or satisfy independent reviewer requirements.
