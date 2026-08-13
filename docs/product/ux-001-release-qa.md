# UX-001 Release QA Matrix

**Applies to:** UX-001 final hardening and rollout  
**Product boundary:** Existing implemented capabilities only  
**Automated browser baseline:** Chromium on the repository Linux CI runner

This checklist closes UX-P9 without changing domain behavior. Browser screenshots detect shell/layout regressions on stable routes; authenticated domain-heavy screens remain covered by the existing PHP/architecture contracts plus the manual matrix below because their visual state depends on seeded Alliance/Kingdom data.

## Automated release gates

The dedicated visual-regression workflow must:

- install the locked PHP and frontend dependencies;
- migrate a disposable PostgreSQL database;
- create one verified visual-test account with no fabricated Alliance/domain data;
- build the production frontend assets;
- run the application with persistent file-backed sessions;
- install the locked Playwright Chromium runtime;
- compare committed desktop and mobile screenshots for Home, Login, Register, Forgot Password, and the authenticated Dashboard shell;
- compare English and Arabic RTL baselines for Home, Login, and the authenticated Dashboard shell;
- emulate `prefers-reduced-motion: reduce`;
- assert document `lang`/`dir` for LTR and Arabic RTL;
- assert keyboard access from the authenticated skip link to `#main-content`;
- upload Playwright output when the browser gate fails.

Normal CI, CodeQL, and Dependency Review remain separate required evidence. The browser workflow does not replace domain tests.

## Manual representative-route matrix

Review at 1440px desktop, approximately 768px tablet, and approximately 390px mobile. Use real seeded fixtures appropriate to the route; do not invent metrics or workflow states for visual convenience.

| Journey | Representative routes | Primary review |
|---|---|---|
| Public | `/`, public Alliance profile/content/recruitment | heading hierarchy, wrapping, public navigation, forms, empty/error states |
| Identity | `/login`, `/register`, password reset/verification/2FA flows | labels, validation, keyboard order, focus visibility, long strings |
| Application shell | `/dashboard`, `/profile` | skip link, navigation, mobile drawer, locale switcher, active-Alliance gating |
| Alliance operations | Alliance overview, Events index/detail/coordinator | dense controls, tables/cards, event time presentation, mobile actions |
| Roster/contributions | roster/index/manage/history/intelligence, contribution member/manager | table overflow, mobile cards, filters, numerical formatting, empty states |
| Recruitment/content/integrations | manager/detail/content/integration pages | long authored text, form grouping, one-time secret/token presentation |
| Kingdom | overview/settings/ingestion/diplomacy/contacts/history/intelligence/sharing | semantic tables, factual-state badges, private-manager fields, narrow layouts |
| Transfers | overview/manage/readiness/completion | direction/readiness terminology, blockers, lifecycle actions, mobile participant cards |
| Platform | `/platform/administration` | privileged-control hierarchy, fleet tables/cards, lifecycle danger states, locale runtime inventory |

## Locale review tiers

### Tier A — primary visual QA

`en`, `fr`, `pt-BR`, `ar`, `es`, `de`

For every representative journey, verify no clipped labels, inaccessible controls, overlapping text, or hard-coded newly introduced English. Arabic receives the full major-journey RTL walkthrough.

### Tier B — complex-script/layout QA

`ja`, `ko`, `zh-CN`, `zh-TW`, `th`

Verify CJK/Thai wrapping, display-heading behavior, compact table/card labels, and that numeric/game data remains understandable without inappropriate mirroring.

### Tier C — catalogue and stress QA

`id`, `it`, `pl`, `ru`, `tr`, `vi`

Confirm catalogue availability and representative operational screens. German and Russian receive explicit long-string stress review across buttons, tabs/links, cards, filters, and table headers.

## Accessibility checklist

- Exactly one primary `main` landmark per shell page.
- Skip links become visible on keyboard focus and target a focusable main-content element.
- Native form controls retain explicit labels and validation messages.
- Buttons and links remain keyboard reachable; visible focus is never removed without a replacement.
- Information is not conveyed by color alone when state text already exists.
- Desktop semantic tables retain captions/headers where the page contract requires them; mobile card alternatives do not remove the desktop table semantics.
- Destructive/lifecycle actions retain explicit wording and existing confirmation boundaries.
- Focus order follows source order after responsive reflow.
- Forced-colors mode keeps visible focus and control boundaries.

## Responsive and international-layout checklist

- No page-level horizontal scrolling at the target mobile width except intentionally scrollable data regions.
- Long action labels may wrap rather than clipping or shrinking below usable size.
- Flex/grid children can shrink without forcing viewport overflow.
- Arabic uses logical start/end shell positioning and `dir="rtl"`; email/URL/telephone values remain LTR where needed.
- CJK text uses appropriate strict line breaking while preserving numbers and identifiers.
- Thai and long Latin strings wrap without overlapping neighboring controls.
- Tables that cannot become cards remain contained in an intentional horizontal overflow region.

## Motion, media, and layout stability

- `prefers-reduced-motion: reduce` suppresses non-essential animation/transition duration and smooth scrolling.
- Product pages use local/system font stacks; the release does not depend on a blocking remote web-font request.
- Responsive images/media remain constrained to their container.
- Image dimensions or stable aspect-ratio containers should be supplied when a product image is known at render time; authored remote content must not be given fabricated dimensions.
- Avoid decorative effects that materially delay interaction on operational pages.

## Final terminology review

Use Kingshot/domain terminology already established by the product: Alliance, Kingdom, Roster, Rally, Formation, Recruitment, Diplomacy, Intelligence, Transfers, Incoming, Outgoing, Staying, Readiness, and Completion. Do not replace these with generic SaaS vocabulary when the game concept is known.

Dynamic Alliance-authored text and domain-owned observations/settings remain application data, not translation strings.

## Rollout evidence

Before UX-001 is marked complete:

1. normal CI is green on the final clean SHA;
2. CodeQL and Dependency Review are green on the same SHA;
3. the visual-regression workflow is green against committed baselines;
4. the workflow directory contains only intentional permanent workflows;
5. no temporary trigger/marker/apply files remain;
6. the PR remains unmerged until explicit merge approval;
7. the UX-001 plan/PR record the final P9 boundary and any known non-blocking visual debts.
