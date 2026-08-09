# KINGDOMS-001 accessibility review

[← Product and program documentation](README.md)

**Scope:** `KINGDOMS-001` whole-increment hardening / `K1-P6`  
**Status:** Accepted repository accessibility evidence

This review covers the complete first-party Kingdoms workflow: Alliance→Kingdom settings, member roster, roster management, player snapshot history/recording, roster intelligence and controlled CSV migration.

## Semantic structure and navigation

All six surfaces retain a `<main>` landmark and one primary `<h1>`. Navigation uses normal links, and actions use native buttons rather than clickable containers or custom `role="button"` widgets.

The roster, history, intelligence and CSV-preview data use semantic tables with header cells. Their table containers allow horizontal overflow so narrow viewports can reach every column rather than clipping data.

## Forms and labels

Kingdom settings, roster filters, roster management, snapshot recording and CSV upload use explicit labels associated with native inputs/selects/textareas.

`K1-P6` closes the remaining CSV ambiguity-control gap by giving each row-resolution select a programmatic accessible label that includes the CSV row number and player name. Managers therefore do not need table position or visual proximity alone to identify what is being resolved.

Kingdom-number and CSV-upload server validation also expose `aria-invalid`/described error text. Row/file errors use alert semantics, while successful commit summaries use a polite status region.

## Keyboard interaction

The increment introduces no custom keyboard model. Standard browser keyboard behavior applies to links, inputs, selects, file upload and buttons.

The roster departure confirmation uses the browser's native confirmation dialog rather than a custom modal/focus trap. CSV ambiguity resolution uses native selects. No drag-and-drop, hover-only action or pointer-only control is required for the accepted workflow.

## Meaning and state

Current/stale/missing snapshot quality, roster state, CSV preview outcome, linkage state and import status are rendered as text. The workflow does not rely on color alone to communicate those states.

The intelligence page describes missing-data behavior and trend-window semantics in text. Manager comparison detail is explicitly described as alphabetical diagnostic information rather than a ranking.

## Errors and status feedback

Server-side validation remains authoritative through Laravel/Inertia. User-facing forms retain visible inline error text. `K1-P6` strengthens the highest-risk CSV and Kingdom-setting errors with alert/association semantics and preserves disabled-state feedback for in-flight actions.

The CSV page communicates row-level validation failures, unresolved ambiguity count, rejected-batch refusal, commit failure and successful atomic-import summary.

## Responsive behavior

Primary layouts use responsive grid/flex wrapping. Dense roster/history/intelligence/import tables are intentionally horizontally scrollable at narrow widths rather than collapsing columns into ambiguous unlabeled values.

No fixed-width interaction is required to complete Kingdom settings, roster creation/editing, snapshot recording or CSV ambiguity resolution.

## Motion and media

The increment adds no autoplay, flashing content, timed animation, audio/video or motion-dependent interaction.

## Automated verification

`tests/Architecture/KingdomAccessibilityTest.php` protects the source-level invariants for:

- main landmarks and primary headings;
- native controls rather than pseudo-buttons;
- explicit labels on normal form surfaces;
- programmatically labelled per-row CSV ambiguity resolution;
- import status/error live-region semantics; and
- semantic, horizontally scrollable tables.

The protected implementation gate at `7f743507b70865692290f517cd2de494ec54abae` passed frontend formatting, lint, Vue/TypeScript checks and the production build together with the complete repository test suite. Exact evidence is recorded in the [KINGDOMS-001 exit report](kingdoms-roster-intelligence-exit-report.md).

## Manual release QA boundary

Repository acceptance verifies source semantics and the protected frontend build, but it does not truthfully prove every browser/assistive-technology combination. Release QA should still smoke-test keyboard-only navigation, 200% zoom/reflow, screen-reader landmark/heading/form navigation and validation announcements on the Kingdom settings, roster-management, snapshot and CSV surfaces.

That recommended manual smoke test is not represented as completed evidence unless an accountable operator records it separately. It does not change the repository/product acceptance distinction from real production-cutover approval.
