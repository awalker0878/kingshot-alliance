# Phase 6 accessibility review

Phase 6 adds the platform administration console, alliance integration-management page, and account-deletion page. These surfaces use semantic headings, forms, fieldsets/legends for grouped controls, labels for inputs, native buttons/links, tables for tabular operational data, and `role="status"` for mutation feedback.

## Keyboard and focus

All actions use native interactive controls and are keyboard reachable. Destructive lifecycle/account actions are buttons rather than clickable containers. Navigation remains standard links. No Phase 6 dialog traps or custom widgets are introduced.

## Meaning and state

Security-sensitive controls use visible text labels rather than icon-only affordances. Platform and integration states are rendered as text (`Active`, `Revoked`, lifecycle states, delivery status) so color is not the sole indicator. One-time API/webhook secrets are placed in selectable code blocks with explanatory headings.

## Forms and errors

Inputs have associated label text; grouped API scopes use a `fieldset`/`legend`. Server-side Laravel validation remains authoritative and Inertia preserves standard validation behavior. Password confirmation is an existing accessibility-reviewed flow and is reused rather than reimplemented.

## Tables and overflow

Fleet, credential, and delivery tables have header cells and horizontal overflow containers so narrow viewports do not clip information. Operational values remain text-readable and do not depend on charts.

## Motion and media

No animation, autoplay, flashing content, or new media playback is introduced by Phase 6.

## Verification

Frontend lint/type/build remains a protected gate. Phase 6 adds source-level accessibility guard coverage for headings, labeled forms, native button semantics, status feedback, and the absence of icon-only destructive controls. Manual launch review should include keyboard-only navigation, browser zoom to 200%, screen-reader heading/form-landmark navigation, and validation-error announcement checks on the three new pages.
