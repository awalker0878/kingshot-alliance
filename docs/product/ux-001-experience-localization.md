# UX-001 — Kingshot Alliance Experience and Localization

**Status:** Active  
**Program type:** Product experience modernization  
**Scope:** Existing implemented capabilities only  
**Primary frontend:** Laravel + Inertia + Vue 3 + TypeScript + Tailwind CSS 4

## 1. Objective

Modernize the Kingshot Alliance web experience so it feels purpose-built for Kingshot players, aligns closely with the approved dark navy/gold/blue visual direction, and supports the major languages used by the Kingshot audience without changing the accepted domain model or inventing unsupported product functionality.

This is a presentation, interaction, accessibility, responsive-design, and localization program. Existing Laravel/domain behavior remains authoritative.

## 2. Non-negotiable product rules

### 2.1 Existing functionality only

A generated mockup is a visual target, not a source of new business requirements.

Before redesigning any page family, implementation must inspect the current route, controller, authorization boundary, Inertia payload, Vue page, and existing actions/forms. The approved redesign may reorganize, clarify, or improve those capabilities, but it must not introduce fake or aspirational behavior.

Examples of functionality that must not be added merely because a concept image contains it:

- social login providers;
- magic-link authentication;
- generic direct messaging;
- generic notification centers;
- unsupported analytics;
- unsupported reminder systems;
- fake help-center/status/social links;
- arbitrary statistics that are not supplied by the current application.

If a future feature is desired, it must enter the normal product/domain planning process separately.

### 2.2 Kingshot-first information architecture

The interface must prioritize concepts meaningful to Kingshot players and alliance leadership, including existing capabilities such as:

- alliances and alliance membership;
- kingdoms;
- members and rosters;
- power and activity where the application already tracks them;
- events;
- rallies and formations;
- registrations and attendance;
- recruitment;
- alliance contributions;
- alliance content;
- diplomacy;
- kingdom intelligence/history/sharing;
- transfer planning, readiness, destinations, incoming/outgoing/staying members, and completion;
- integrations that are actually implemented;
- public alliance profiles and recruitment applications.

Generic SaaS terminology must not replace game-domain language when the Kingshot concept is already known.

### 2.3 Visual direction

The visual target is a premium strategy-game operations experience:

- deep navy / near-black foundations;
- restrained gold brand hierarchy;
- blue interactive states;
- green success, amber warning, red danger, purple information/role accents;
- castle, banner, crest, shield, map, and strategy motifs where appropriate;
- serif display typography for public/marketing emphasis;
- highly legible sans-serif UI typography for dense operational screens;
- subtle atmospheric effects on public/auth surfaces;
- quieter, clearer backgrounds on data-heavy authenticated screens;
- consistent elevation, borders, spacing, radius, iconography, focus, hover, and disabled states.

Public pages may be cinematic. Operational pages must prioritize clarity and speed over decoration.

## 3. Localization target

The localization architecture must support the major languages exposed by Kingshot's international audience from the beginning of the program.

| Locale | Native name | Direction |
|---|---|---|
| `en` | English | LTR |
| `ar` | العربية | RTL |
| `de` | Deutsch | LTR |
| `es` | Español | LTR |
| `fr` | Français | LTR |
| `id` | Bahasa Indonesia | LTR |
| `it` | Italiano | LTR |
| `ja` | 日本語 | LTR |
| `ko` | 한국어 | LTR |
| `pl` | Polski | LTR |
| `pt-BR` | Português (Brasil) | LTR |
| `ru` | Русский | LTR |
| `th` | ไทย | LTR |
| `tr` | Türkçe | LTR |
| `vi` | Tiếng Việt | LTR |
| `zh-CN` | 简体中文 | LTR |
| `zh-TW` | 繁體中文 | LTR |

`pt-BR` is the initial Portuguese variant. The architecture must permit adding `pt-PT` later without redesigning locale handling.

### 3.1 Visual QA tiers

All supported locale catalogues are required to remain structurally complete as pages are migrated. Visual regression effort is prioritized as follows:

**Tier A — primary visual QA:** `en`, `fr`, `pt-BR`, `ar`, `es`, `de`  
**Tier B — CJK/complex layout QA:** `ja`, `ko`, `zh-CN`, `zh-TW`, `th`  
**Tier C — complete catalogue:** `id`, `it`, `pl`, `ru`, `tr`, `vi`

Arabic receives dedicated RTL testing across major user journeys.

## 4. Localization rules

- No newly redesigned user-facing page may add hard-coded English for translatable UI text after the localization foundation is enabled.
- Translation keys are semantic, for example `events.registration.join`, not full English sentences used as keys.
- Game-domain translator notes must explain ambiguous concepts such as Power, Rally, Formation, Transfer Destination, Incoming, Outgoing, and Staying.
- Locale names are displayed in their own native form.
- Dates, times, numbers, percentages, and relative time use locale-aware formatting.
- Canonical stored event times remain unchanged; localization affects presentation only.
- Arabic sets the document direction to RTL and uses logical start/end layout semantics.
- Numeric/game diagrams are not blindly mirrored when their meaning is directional or chronological.

## 5. Delivery model

Every phase follows the same gated workflow:

1. inspect current implementation;
2. inventory actual user-visible capabilities and data;
3. generate one or more high-fidelity image batches using only those capabilities;
4. refine/approve the visual target;
5. implement shared components/page redesign;
6. test desktop, tablet, mobile, localization, RTL, accessibility, and existing behavior;
7. update documentation/tests only where the accepted contract changed.

Large page families may require multiple image-generation turns before implementation. The user may continue the program by sending `continue`; unless explicitly rejected, the latest visual direction is treated as accepted for implementation.

## 6. Phase plan

### UX-P0 — Visual system and information architecture

Deliverables:

- color/surface/token system;
- typography hierarchy;
- spacing/radius/elevation system;
- buttons, inputs, cards, tables, badges, alerts, tabs, empty/loading/error states;
- public, auth, and authenticated shell definitions;
- Kingshot-specific navigation hierarchy;
- responsive and RTL shell mockups.

Image batches:

- style board, component board, typography/state board, data-density board;
- public desktop shell, app desktop shell, public mobile shell, app mobile shell;
- Arabic RTL shell, CJK shell, German long-string stress shell as needed.

### UX-P1 — Shared frontend and localization foundation

Deliverables:

- shared brand/UI/navigation component hierarchy;
- theme tokens in `resources/css/app.css`;
- locale registry and translation runtime;
- locale resolution and document `lang` / `dir` behavior;
- locale-aware number/date/relative-time formatting helpers;
- eventual authenticated locale persistence contract;
- CI checks for locale catalogue parity as catalogues are introduced.

No domain behavior changes are permitted in this phase.

### UX-P2 — Homepage and public experience

Current feature families to redesign:

- `/` homepage;
- public alliance profile;
- public alliance content;
- public recruitment application.

Homepage messaging may highlight only implemented product capabilities, such as alliance event coordination, roster management, recruitment, kingdom planning, diplomacy/intelligence, transfer planning, alliance content, and public alliance profiles.

The homepage must provide obvious routes to the currently implemented sign-in and registration experiences and may expose invitation entry only through the existing invitation flow.

Image batches:

- homepage full desktop, hero detail, authenticated-home state, mobile;
- public alliance, public content, recruitment application, mobile;
- locale variants including Arabic, Japanese/Chinese, German, and Portuguese.

### UX-P3 — Authentication and onboarding

Existing screens:

- Login;
- Register;
- Invitation;
- Forgot Password;
- Reset Password;
- Verify Email;
- Confirm Password;
- Two-Factor Challenge.

The design must not add social login or magic-link authentication unless separately implemented by the Identity domain.

Image batches:

- login desktop/mobile, registration, invitation;
- forgot/reset/verify/two-factor;
- success/error/validation states and Arabic RTL.

### UX-P4 — Application shell, dashboard, profile, and security

Deliverables:

- persistent authenticated shell;
- responsive alliance navigation;
- user/account menu;
- locale switcher;
- contextual page headers/actions;
- dashboard redesign based only on the real Dashboard controller payload;
- profile/password/email/locale/timezone/two-factor/session/account-deletion redesign.

Any dashboard card shown in a mockup must correspond to data already returned by the application.

### UX-P5 — Alliance operations: overview, events, roster, contributions

Page families include the currently implemented Alliance overview, Events index/show/manage flows, roster/index/manage/import/history/intelligence flows, and contribution views.

Image batches must cover real event concepts such as registration, formations, rally assignments, reminders, attendance, and existing management actions only where those actions are currently implemented.

### UX-P6 — Recruitment, content, and integrations

Recruitment redesign must be based on the existing candidate/stage/reviewer/note/tag/communication/onboarding/conversion capabilities.

Content redesign covers existing member/public content and management workflows.

Integration redesign exposes only actual integrations and their actual configuration/health states.

### UX-P7 — Kingdom, diplomacy, intelligence, sharing, and transfers

This is intentionally the largest visual phase and may use several image-generation turns.

Subphases:

- UX-P7-A — kingdom overview/settings/ingestion;
- UX-P7-B — diplomacy, contacts, history, intelligence;
- UX-P7-C — kingdom sharing and management;
- UX-P7-D — transfer plans, transfer management, readiness, destination planning, and completion.

Mockups must be generated from the actual controller/page payloads before implementation.

### UX-P8 — Platform administration and localization operations

Redesign the existing platform-administration surface. Localization administration may be added only to the extent that it is infrastructure needed to operate the localization capability introduced by this program; it must not invent unrelated platform features.

Normal users receive language/timezone preferences through the application/profile experience rather than an administrator-oriented translation dashboard.

### UX-P9 — Responsive, accessibility, RTL, visual regression, and rollout

Release gates:

- WCAG AA-oriented keyboard/focus/contrast/semantic review;
- desktop/tablet/mobile breakpoints;
- Arabic RTL walkthrough of major journeys;
- CJK typography/layout review;
- German/Russian long-string stress testing;
- locale catalogue parity checks;
- visual regression baselines for key routes;
- reduced-motion behavior;
- image/font/layout-shift/performance review;
- final content and terminology review.

## 7. Shared component target

The frontend should converge toward a structure similar to:

```text
resources/js/
  components/
    brand/
    data/
    feedback/
    forms/
    navigation/
    ui/
  layouts/
    PublicLayout.vue
    AuthLayout.vue
    AppLayout.vue
  localization/
    locales.ts
    messages/
  composables/
  pages/
```

Core reusable primitives should include, as they become necessary:

- brand mark/wordmark;
- locale switcher;
- buttons/icon buttons;
- inputs/selects/checkboxes/toggles/textareas;
- cards/metric cards;
- badges/alerts;
- tabs/dropdowns/modals;
- page headers/breadcrumbs;
- data tables/pagination;
- skeleton/loading/empty/error states.

Do not build unused abstractions merely to satisfy this list; introduce components when a real page family needs them.

## 8. Definition of Done for redesigned pages

A redesigned page is complete only when:

- it exposes only implemented functionality;
- it uses approved Kingshot terminology;
- it matches the accepted visual target closely;
- domain authorization and backend behavior remain unchanged unless separately approved;
- newly introduced translatable UI uses locale keys;
- required locale catalogues contain the migrated keys;
- Arabic RTL is valid where applicable;
- CJK and long-string layouts are reviewed where applicable;
- desktop, tablet, and mobile layouts are intentional;
- keyboard/focus behavior is usable;
- relevant loading, empty, validation, success, and error states are designed;
- TypeScript/lint/format/build checks pass;
- affected PHP/domain tests remain green;
- documentation and architectural tests are updated if the accepted contract changed.

## 9. Program boundaries

UX-001 does not replace the current product/domain implementation plan, domain contracts, security model, deployment architecture, or production approval process. It is a normal change-driven modernization program operating within those accepted boundaries.

Historical acceptance evidence remains historical. New UX/localization work receives new commits/tests/evidence without rewriting prior phase history.
