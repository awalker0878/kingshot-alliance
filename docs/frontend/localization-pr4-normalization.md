# PR 4 — shared locale normalization contract (draft)

**Integration order:** merge #187, then #181, then this PR. The November 5 follow-up includes those runtime fixes as a merge parent; the two #183 catalogue moves are retained. Earlier evidence below is historical, not validation of the new head.

## OWNER-APPROVED STRICT RAW HEADER POLICY (November 2, 2026)

- [Written PHP standalone normalization approval](https://github.com/awalker0878/kingshot-alliance/pull/184#issuecomment-6591545289); [separate written raw-header approval](https://github.com/awalker0878/kingshot-alliance/pull/184#issuecomment-6591575657). Both are owner directives, **not** a GitHub code-review approval. PR remains draft.
- Existing strict `SupportedLocale::normalize()` remains unchanged. It rejects NULs, overlong standalone tags, and invalid standalone syntax. Cookies are validated first, then (if invalid/absent) raw `Accept-Language` candidates are processed.
- New `AcceptLanguageLocale::firstSupported()` does **not** call Symfony's lossy `Request::getLanguages()`. It splits individual comma-delimited ranges, validates each complete raw tag and optional `q` parameter before normalization, discards malformed or >128-byte candidates, rejects q=0, sorts supported candidates by descending integer thousandths with original-position tie-breaking, and falls back to English when none are acceptable. An invalid entry **never discards a later valid range**, even after garbage separators.
- The accepted **application profile** is deliberately narrower than every conceivable valid BCP-47 tag: primary language (two or three letters), optional four-letter script, optional two-letter/three-digit region, and complete extension or private-use sequence. Unknown free-form variants, underscores in HTTP ranges, `*`, extra parameters, invalid q values and malformed script/region aliases are rejected. This is an explicit compatibility tradeoff, not a claim of fully general RFC 4647 matching. Existing valid regional/script/extension examples and eight realistic browser header fixtures must remain green.
- Explicit negatives: 245-byte French range `fr-ab-…-cd;q=1` must **not** be truncated to `fr_AB` by Laravel and must result in English with unsupported backup; `zh-hantX` must not become `zh-CN`, and `pt-brX` must not become `pt-BR`. Valid ranges following malformed inputs still win by quality. No 4xx is produced.
- The earlier rejected design changed the overlong assertion from `en` to `fr`. **That was withdrawn and is not the approved policy.** Keep `en`; add raw-header characterization and quality tests. No concrete exploit or production incident has been established.
- **Validation gates:** exact final-head PHP/frontend/visual, independent reviewer approval, container/staging if required; consider unexpected real-world header profiles before release. Do not merge while these gates are unresolved.

## First implementation slice

- Use one canonical **behavior contract fixture** to test the Laravel and TypeScript normalizers. Accept valid BCP-47-like language, region and script tokens, underscore separators, canonical Brazilian Portuguese and Simplified/Traditional Chinese aliases, and recognized primary-language fallbacks.
- Reject malformed input such as traversal paths, invalid separators and deceptive `pt-brX` / `zh-cnX` prefixes, rather than silently treating malformed tags as valid preferences.
- Provide matching `firstSupported` / `selectFirstSupportedLocale` pure functions to express *ordered* candidate preference. Laravel request middleware consumes cookie first and browser Accept-Language second, preserving the existing server precedence.
- Add shared fixture-driven PHP and source-contract tests; no authority or identity is inferred from locale.

**Not in this slice:** setting the client's cookie/localStorage, startup reconciliation, last-observed cookie, multi-tab ABA/version/epoch, or account preference API. That is PR 5B after both this normalization contract and PR 5A's generation-safe commit lifecycle are merged. Avoid changing `resources/js/localization/index.ts` in PR 4 while 5A settles.

## Acceptance

- [ ] Frontend source-contract tests and Laravel `SupportedLocaleTest` / `RequestLocaleTest` pass.
- [ ] Shared fixture agrees between PHP and TypeScript on every valid, invalid and priority candidate.
- [ ] Full CI and representative browser tests confirm no unexpected frontend/server locale change.
- [ ] Clarify on-page selector precedence with PR 5B; do not add competing normalization.
- [ ] Confirm canonical 17 locales and their aliases remain stable.

## Compatibility decision — baseline PHP is not behaviour-identical

Verified in [CI 41513069567](https://github.com/awalker0878/kingshot-alliance/actions/runs/41513069567) by executing both original and new PHP **and** TypeScript normalizers against a 52-input shared fixture. Exactly **16 concrete inputs** changed in the matrix. All new PHP/client results agree. This does not prove equivalence for all possible strings.

| Changed input | Original TS | Original PHP | New TS + PHP |
|---|---|---|---|
| `en--US` | en | en | null |
| `fr-CA-` | fr | fr | null |
| `zh-cnX` | zh-CN | null | null |
| `pt-brX` | pt-BR | pt-BR | null |
| `zh-hantX` | zh-TW | null | null |
| `en\\u0000` (actual NUL) | null | en | null |
| `fr-CA;q=0.8` | fr | fr | null |
| `fr-CA,fr;q=0.8` | fr | fr | null |
| `fr-CA x` | fr | fr | null |
| `fr__CA` | fr | fr | null |
| `pt_brX` | pt-BR | pt-BR | null |
| `pt-BR;q=1` | pt-BR | pt-BR | null |
| `fr-` plus 80 repeated `ab-` tokens and `cd` | fr | fr | null |
| `ar-u-` | ar | ar | null |
| `en-x-a` | en | en | null |
| `fr-CA\\u0000` (actual NUL) | fr | fr | null |

**Historical context (superseded by the two written owner approvals above):** Rejecting these malformed values means Laravel now ignores the cookie and uses the next browser Accept-Language candidate (or English), rather than accepting its misleading prefix. That is intentional fail-closed validation, but potentially observable behaviour; PHP owners must explicitly approve it. Supported locales and valid tags, including `pt_BR`, `pt-br`, `ar-u-nu-latn` and `zh-Hant-HK`, remain accepted. Accept-Language q-weights are parsed by the validated raw-header selector rather than being passed to standalone normalization.

**Rollback boundary:** Treat `SupportedLocale`, `AcceptLanguageLocale`, `ResolveRequestLocale`, their shared fixtures and PHP/request tests as one strict-policy change. Do not revert only the raw-header selector while retaining expectations that depend on it. The November 5 whitespace follow-up preserves PHP behaviour and changes only TypeScript trimming, fixtures and diagnostics; reverting it reopens the cross-runtime whitespace gap. Runtime work inherited from #181/#187 has its own rollback boundary. `SupportedAllianceLocale` remains untouched. No 5B preference synchronization is introduced by this PR.


**Full comparison:** the job stores `localization-pr4-compatibility-delta.json` for exact long input strings. The 52-case finite matrix is not a proof of exhaustive behaviour across infinitely many possible strings.

## November 5 whitespace parity follow-up

The current fixture has **73 standalone cases**, **9 ordered-preference cases**, **11 cookie/header cases**, **21 malformed-header cases**, and **8 realistic browser examples**. The compatibility report derives these counts from data and annotates standalone inputs with explicit Unicode code points. Against the original `a5151e7` baseline, 23 enumerated inputs change in at least one runtime; all 73 now agree between PHP and TypeScript.

Preserve the approved PHP standalone policy: reject NUL first; trim boundary U+0020, U+0009, U+000A, U+000D and U+000B. Reject NBSP (U+00A0), BOM (U+FEFF), form feed (U+000C), EM SPACE (U+2003), line/paragraph separators, and embedded whitespace. Raw HTTP ranges still trim only SP/HTAB and reject controls; valid lower-weight candidates survive malformed ranges.

Local validation after integration: two frontend fixture tests; 11 PHP/unit/request tests, 218 assertions; compatibility, raw-header diagnostics and eight browser-preference examples passed. These finite fixtures do not prove universal language-tag equivalence. Final-head CI, browser execution and independent HTTP/PHP review remain required.
