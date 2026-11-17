# Audit stabilization

The October 25, 2026 audit reviewed `0eeccd1f7844af44117ded0e5b2278e94cf34f78`.
This first implementation batch repairs release regressions and localization/time-display contracts.

## Implemented changes

| Audit item | Change | Verification |
| --- | --- | --- |
| IMP-01 | Separate malicious-SVG and outsider artifact tests. A previous owner HTTP request retained its `auth.session` password hash when `actingAs` changed users, reaching authentication instead of artifact authorization. The outsider test retains 403 assertions, checks that artifact data is absent, and verifies that the outsider remains authenticated. | PHP quality/test and KingdomMaps CI suites. No production authorization rule is relaxed. |
| IMP-02 | Keep the object list and keyboard focus visible after selection; the Map tab deliberately switches panels and frames the selected object. Scope the responsive-navigation test to the Explorer and use the actual Governor-cities label. | Territory Accessibility across desktop/mobile/tablet/RTL and Layout Tools browser tests. |
| IMP-03 | Add missing cancellation, leadership-transfer, contribution-quality and recruitment labels; activate required page domains. Correct the notice publication heading to use a label without a date parameter. | Real-catalogue key resolution across 93 pages and their component dependencies. |
| IMP-04 | All verification descriptions refer to the separately displayed email address; no `{email}` placeholder remains in those descriptions. | Placeholder parity for all 17 locales. |
| IMP-05 | Agenda, attention cards, reminders, details and calendar grouping use the account timezone. Details also identify the event timezone. Calendar cells use UTC-backed civil dates to avoid browser-zone and DST drift. CSV and iCalendar retain their existing explicit UTC instants. | Toronto/UTC/São Paulo/Baghdad, day-boundary, DST and leap-year tests; browser scenario with Toronto browser and UTC account. |
| IMP-06 foundation | Add `npm run check:localization-contracts` to the required frontend check. Parse real calls, follow component/composable dependencies including literal async imports, load actual Vite catalogues/overlays, and reject unresolved keys or incompatible/missing placeholders. | Scanner regression tests plus the complete repository check. |

The event-command extension now belongs explicitly to Event Management, the only page supplied with that projection. It loads asynchronously so other room banners do not acquire an Events dependency and the page remains within the existing performance budget.

The first pull-request run also exposed newly published dependency advisories. The follow-up updates the three affected Composer packages (`league/commonmark` 2.10.4, `livewire/livewire` 4.4.8 and `symfony/http-foundation` 8.1.9) and the affected npm dependency chains. ESLint's Vue plugin moves to version 10 with explicit browser, Node and service-worker globals. Audit thresholds and application PHP requirements are unchanged.

The production image also upgrades inherited `curl` and `libcurl` to at least Alpine's 8.23.0-r0 packages. The image scan found CVE-2026-92392 in the base image's 8.22.0-r0 packages even though application dependency audits passed.

## Validation and limits

- `npm run check` includes lint, formatting, TypeScript, 198 source-contract tests, localization, accessibility, geometry/export/art structure, production build and unchanged performance budgets.
- At commit `bee4700`, PHP 8.5 CI passed all 2,101 tests and 99,670 assertions; frontend CI, dependency review, Architecture V3 and CodeQL passed. The container job passed image build, ephemeral staging, and backup/restore before its curl vulnerability finding. Image-scan acceptance must be rerun after the package upgrade.
- At commit `1a70b72`, KingdomMaps CI passed the territory owner/read-model tests and the visual run passed all six previously failing Explorer accessibility/layout scenarios. The strict artwork gate still correctly rejects 24 source-missing families.
- PHP 8.5/PostgreSQL and the full browser application are validated by the existing pull-request CI workflows. A temporary PHP 8.3 CLI was used only to regenerate and audit the Composer lock with platform requirements ignored during resolution; the three patched packages retain PHP requirements compatible with the application's unchanged PHP 8.5 requirement. CI remains the runtime acceptance gate.
- Both dependency audits report no known vulnerabilities after the updates. Browser acceptance requires a passing pinned-environment run: initial runs had screenshot-fingerprint mismatches and timezone-test setup errors. The test now handles a missing Governor switcher and reads the actual initial page payload, avoiding an invalid versionless Inertia request.
- The visual workflow pins Ubuntu 24.04 and Chrome 152.0.7977.82, the recorded baseline environment. GitHub's floating `ubuntu-latest` moved to Ubuntu 26.04 and Chrome 155 between the September 16 baseline run and October 26 PR run. The pinned browser download is checksum-verified. Controlled server-rendering comparisons of captured page data found identical normalized markup on 13 unaffected surfaces and byte-identical stylesheets; the notice publication label and event timezone surfaces changed intentionally. This evidence narrows the visual investigation but does not replace browser screenshots or justify blanket fingerprint updates.
- The pinned run at `870967d` passed 88 browser cases, including both new timezone scenarios, and restored all 17 unexplained fingerprint failures. The two notice-detail screenshots were visually reviewed in that run's artifact and their fingerprints updated solely for the corrected publication label. Transfer tests retain their complete-raster option and now preserve the pinned executable when overriding launch options. All other fingerprints, tolerances, retries and assertions remain in force.
- The localization gate checks literal keys and conditional literal alternatives. It reports dynamic calls for the existing domain-specific tests; it does not claim exhaustive dynamic-key verification or native-speaker review.
- English fallback remains a resilience mechanism. Passing this gate does not mean every locale is fully translated.

## Next batches

1. Complete IMP-06 with typed key/parameter generation, enumerated dynamic-key inventories and reviewed translation-coverage reporting.
2. Persist account locale across devices and server/mail/delivery paths (IMP-07); consolidate English-only extensions and translate complete journeys (IMP-08/09).
3. Implement accessible mobile navigation and simplify home/navigation (IMP-10/11), followed by focused workflow panels and Assistant language support (IMP-12/13).
4. Add catalogue-load retry feedback and interaction-performance measurements (IMP-14).
5. Use the artwork workbook to obtain and review real map references, then produce/ingest masters and responsive scene assets (IMP-15/16). The 24 source-missing map families remain open; no placeholder is marked approved.
6. Close production acceptance only after the exact candidate SHA and deployment/recovery evidence pass (IMP-17).
