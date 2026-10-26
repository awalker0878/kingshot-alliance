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

## Validation and limits

- `npm run check` includes lint, formatting, TypeScript, 198 source-contract tests, localization, accessibility, geometry/export/art structure, production build and unchanged performance budgets.
- PHP 8.5/PostgreSQL and the full browser application are validated by the existing pull-request CI workflows. The implementation workspace does not have those runtimes.
- The localization gate checks literal keys and conditional literal alternatives. It reports dynamic calls for the existing domain-specific tests; it does not claim exhaustive dynamic-key verification or native-speaker review.
- English fallback remains a resilience mechanism. Passing this gate does not mean every locale is fully translated.

## Next batches

1. Complete IMP-06 with typed key/parameter generation, enumerated dynamic-key inventories and reviewed translation-coverage reporting.
2. Persist account locale across devices and server/mail/delivery paths (IMP-07); consolidate English-only extensions and translate complete journeys (IMP-08/09).
3. Implement accessible mobile navigation and simplify home/navigation (IMP-10/11), followed by focused workflow panels and Assistant language support (IMP-12/13).
4. Add catalogue-load retry feedback and interaction-performance measurements (IMP-14).
5. Use the artwork workbook to obtain and review real map references, then produce/ingest masters and responsive scene assets (IMP-15/16). The 24 source-missing map families remain open; no placeholder is marked approved.
6. Close production acceptance only after the exact candidate SHA and deployment/recovery evidence pass (IMP-17).
