# KINGDOMS-003 accessibility review

[← KINGDOMS-003 exit report](kingdoms-alliance-intelligence-exit-report.md)

**Scope:** `KINGDOMS-003` / `K3-P6`  
**Status:** **Accepted repository/source-level accessibility evidence**  
**Validated implementation SHA:** `068c4086744f71d33453734f1f1b05fe1430cbff`

## Review scope

The complete first-party K3 workflow was reviewed across:

- tracked game-side alliance member list;
- manager tracking workspace;
- observation/history workspace;
- diplomacy/NAP workspace;
- manager-private diplomacy contacts workspace; and
- descriptive alliance intelligence dashboard.

This is repository/source-level acceptance evidence. It does not substitute for production assistive-technology/user testing where such testing is required by the deployment process.

## Structural semantics

The K3 page set uses a clear content hierarchy with semantic page containers and primary headings. Architecture tests guard the expected `main` and `h1` structure on every first-party K3 page.

Actions use native links, buttons, inputs, selects and textareas rather than non-semantic clickable containers.

## Forms and management controls

Tracking, observation/correction/invalidation, diplomacy-transition and contact-management controls use explicit labels associated with native form controls.

The source-level accessibility suite guards label/control relationships for first-party K3 forms and keeps privileged operations discoverable through ordinary keyboard-focusable controls.

Validation/error feedback remains part of the normal first-party form flow; the accepted product does not require drag-only, pointer-only or hover-only interaction for K3 mutation workflows.

## Tables and history presentation

Tracked-alliance, observation-history, diplomacy-history, contact and intelligence table views use table headers and horizontally scrollable containers where needed for narrower viewports.

The intelligence table includes an accessible caption and factual column labels. Its default ordering remains neutral rather than communicating a best/worst or threat hierarchy.

Missing, stale, current and review-due states are communicated in text, not solely by color.

## Dashboard filters

The K3 intelligence dashboard exposes fixed-vocabulary filters with explicit accessible labels. The filters cover approved tracking, freshness and diplomacy dimensions without relying on icon-only meaning.

Sorting is factual navigation only; accessible text does not present factual sort order as ranking, targeting or recommendation.

## Member/private presentation

Accessibility and privacy are reviewed together where presentation differs by permission.

Ordinary members can access the safe tracked-alliance and intelligence surfaces under `alliance.view` without receiving manager-only contact/detail controls they cannot use. Manager-only links and workspaces are exposed only when authorized, avoiding dead or misleading controls for ordinary members.

Feature tests verify the member/manager payload split alongside source-level markup checks.

## Keyboard and focus expectations

K3 uses native browser controls for links, buttons, form fields and selects, preserving standard keyboard operation and visible-focus behavior inherited from the application design system.

No accepted K3 workflow depends on custom canvas controls, inaccessible drag interactions, pointer coordinates or hover-only disclosure.

## Automated/source-level evidence

`tests/Architecture/KingdomAccessibilityTest.php` covers all K3 Vue surfaces and asserts the repository's required semantic/form/table patterns.

Whole-increment feature tests additionally exercise first-party member and manager routes, including the complete tracking → observation/history → diplomacy → contact → intelligence workflow.

The exact accepted implementation SHA passed the full frontend quality/type/build and PHP test pipeline.

## Protected evidence

Validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`.

- Dependency Review `31430279647` — success;
- CodeQL `31430279652` — success;
- CI `31430279638` — success;
- frontend lint, pinned Prettier, Vue/TypeScript and production build — success;
- Pint — 483 files;
- PHPStan/Larastan — 345/345, zero errors;
- ParaTest/PHPUnit — 359 tests / 4,824 assertions;
- immutable image/staging/backup-restore/scan pipeline — success.

## Disposition

No repository/source-level accessibility blocker remains for `KINGDOMS-003` acceptance.

`KINGDOMS-003` is **Accepted** for repository/product purposes. Real production cutover and any environment-specific accessibility validation remain separately governed.
