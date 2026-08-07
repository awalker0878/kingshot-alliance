# Phase 4 Accessibility Review — Recruitment

## Scope

This review covers the public recruitment application, private recruiter management page, candidate review page, and the alliance-home recruitment navigation added in Phase 4.

## Implemented baseline

- Each Phase 4 page uses a single semantic `main` landmark.
- Public and private forms use native form controls and explicit labels or purpose-specific accessible names.
- Buttons use native `button` elements and declare `type` where needed.
- Links use native anchors/Inertia links and remain keyboard-operable without custom key handlers.
- Phase 4 pages do not use positive `tabindex` values.
- Authored candidate/recruitment text is rendered through Vue interpolation; raw `v-html` is not used.
- Candidate pipeline and metrics use textual labels rather than color alone.
- Application-question editing uses unique labels/IDs for prompt, type, position, help text, and options.
- Required and active states use native checkboxes with visible text.
- Recruiter navigation is a normal link and appears only when the current user has recruitment-management permission.
- Layouts are mobile-first and allow controls to stack before expanding into multi-column arrangements.

## Automated regression guard

`RecruitmentAccessibilityGuardTest` applies source-level checks to Phase 4 Vue pages, including the established prohibition on raw `v-html`, positive `tabindex`, missing main landmarks, and untyped native buttons. The ordinary frontend lint/typecheck/build pipeline also covers the modified Vue surfaces.

## Keyboard and focus expectations

The recruitment flows do not introduce custom widgets that require bespoke keyboard interaction. Browser-native focus order follows DOM order. Recruiters can navigate the candidate table, question editor, settings forms, templates, and onboarding controls using standard keyboard behavior.

## Forms and validation

Server validation remains authoritative. Inputs have visible labels where practical; compact controls use an `aria-label` only where the visible context already supplies the surrounding meaning. Validation errors must remain textual and must not rely on color alone.

## Remaining release-readiness checks

The staging/release smoke review should verify:

- keyboard traversal through the public application and recruiter management surfaces;
- zoom/reflow at 200% and narrow mobile widths;
- visible focus under production branding;
- label announcement in a representative screen reader;
- contrast after alliance branding is applied;
- table readability and horizontal overflow behavior on narrow screens.

These environment/device checks complement, rather than replace, automated source guards.

## Decision

No known Phase 4 implementation requires an accessibility exception. A regression that removes labels, introduces keyboard traps, relies on color-only status, uses raw HTML rendering, or breaks the established accessibility guard blocks Phase 4 acceptance.
