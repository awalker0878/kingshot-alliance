# PR 4 — shared locale normalization contract (draft)

**Base:** `codex/audit-stabilization` at verified merge `a5151e717020cfe00a32aeddcca6279f9c7042fe`. PR 3 is independent.

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

**Compatibility decision pending:** Rejecting these malformed values means Laravel now ignores the cookie and uses the next browser Accept-Language candidate (or English), rather than accepting its misleading prefix. That is intentional fail-closed validation, but potentially observable behaviour; PHP owners must explicitly approve it. Supported locales and valid tags, including `pt_BR`, `pt-br`, `ar-u-nu-latn` and `zh-Hant-HK`, remain accepted. Accept-Language q-weights should be parsed by the HTTP request class, not accepted as part of one raw locale value.

**Rollback boundary:** The client fixtures, TypeScript normalizer and compatibility-audit tooling are the first independent commit; all three PHP files (normalizer, middleware, PHP fixture regression test) are in a **second distinct commit**. Review or revert that second commit independently. `SupportedAllianceLocale` remains untouched. No 5B cookie/localStorage write code is included.

**Full comparison:** the job stores `localization-pr4-compatibility-delta.json` for exact long input strings. The 52-case finite matrix is not a proof of exhaustive behaviour across infinitely many possible strings.
