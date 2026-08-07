# Phase 3 Accessibility Review — Events and Rallies

## Scope

This review covers the new member event list/detail workflows and the event coordinator dashboard introduced in Phase 3.

## Interaction model

Phase 3 uses native browser controls for primary actions:

- links for navigation and event detail access;
- buttons for registration, cancellation, attendance, and participation actions;
- labelled text, number, date, datetime-local, checkbox, select, and textarea controls;
- fieldsets/legends where a group of troop-ratio inputs forms one logical value.

No interaction requires pointer-only gestures, drag-and-drop, hover-only disclosure, or custom keyboard emulation.

## Keyboard operability

- Event list/detail navigation uses native anchors/`Link` components.
- Join/cancel and coordinator mutations use native `<button>` elements.
- Forms follow DOM order matching the visual flow.
- Selects, date inputs, numeric inputs, checkboxes, and text areas use native focus/keyboard behavior.
- No Phase 3 code removes browser focusability from interactive controls.

## Labels and structure

- Forms use explicit labels associated with controls or enclosing labels.
- The saved-formation troop-ratio group uses a `fieldset` and `legend`.
- Event list/detail pages use a single page heading followed by section/article headings.
- Coordinator areas are grouped under semantic `section` elements with descriptive headings/labels.
- The member reminder inbox uses a labelled section and `aria-live="polite"` so newly returned reminders are announced without interrupting the user.
- Status/error text uses readable text rather than color alone; key validation messages use `role="alert"` where implemented.

## Time comprehension

A core Phase 3 accessibility/usability requirement is avoiding ambiguous event times. Member event pages display:

- the event in the user's configured time zone; and
- the same event in the alliance time zone.

The labels include the time-zone name, rather than relying on color, iconography, or an unexplained offset.

## Responsive/mobile behavior

The Phase 3 pages use responsive single-column-first layouts and progressively add columns at larger breakpoints. Registration/cancellation buttons remain ordinary controls rather than being hidden behind desktop-only hover menus. Rally/formation guidance is presented as stacked cards on narrow screens.

The intended mobile member workflow is:

1. open Events;
2. read local/alliance event time;
3. join or cancel directly from the event card;
4. open event details;
5. read formation/rally guidance and saved formation information.

This does not require a spreadsheet or coordinator-only screen.

## Color and text

Phase 3 follows the existing dark application visual system. Important state is accompanied by text labels such as `registered`, `waitlisted`, `standby`, `attended`, `no-show`, and reminder text rather than being communicated by color alone.

Automated frontend checks remain mandatory, but they do not replace manual contrast/focus review. Final visual acceptance should confirm contrast and focus visibility against the deployed staging theme.

## Reduced ambiguity in game guidance

Recommended troop ratios are printed as numeric percentages and heroes as text. Guidance provenance/effective dates are presented in text when available. Members do not need to infer formation meaning from an image alone.

## Regression expectations

A Phase 3 accessibility regression is release-blocking if it causes any of the following:

- registration/cancellation cannot be completed with keyboard controls;
- a form field loses an accessible label;
- event time is presented without a clear zone;
- guidance becomes image-only or color-only;
- reminder content is unavailable to keyboard/screen-reader users;
- narrow layouts hide a required action or require horizontal interaction for the primary member workflow.

## Evidence still required at release gate

The final Phase 3 staging head must pass the existing frontend lint/type/build pipeline. The exit report must also record a staging keyboard/mobile smoke review of Events, Event Detail, and Coordinator pages before product acceptance.
