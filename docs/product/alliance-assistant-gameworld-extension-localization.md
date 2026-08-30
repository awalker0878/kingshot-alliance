# Alliance Assistant GameWorld extension — localization reconciliation

Status: Current delivered amendment — 2026-08-30

This document reconciles the extension localization implementation with `docs/product/alliance-assistant-gameworld-extension.md`. The primary delivery ledger remains authoritative.

## Locale contract

The base Assistant catalogues remain owned by `resources/js/localization/messages/assistant/<locale>.ts`. The GameWorld extension may layer a typed locale-specific overlay at load time instead of duplicating the complete base catalogue in every file.

The overlay is still part of the **Assistant locale catalogue**: it is merged into the selected locale before message resolution and before English fallback. Therefore a supported non-English locale must resolve every extension key from its own translated overlay; English fallback is not accepted as completion for an extension key.

`resources/js/localization/assistant-gameworld-extension.ts` must:

- declare every supported non-English locale from `LocaleCode`;
- use a strict shared shape so TypeScript fails when a locale omits an extension key;
- translate scope copy, source labels, prompt labels, handoff labels, Game data states, participation states, battle-plan states, transfer states, and territory-plan states;
- preserve message parameters exactly (`{event}`, `{count}`, `{datasetVersion}`, and similar tokens);
- contain presentation text only and no authorization, source-selection, evidence, route, or mutation logic.

The localization loader may deep-merge this overlay only for the `assistant` domain. It must not alter other localization domains or change the existing English fallback behavior for unrelated keys.

## Acceptance criteria

- [ ] English continues to resolve extension keys from the canonical English Assistant catalogue.
- [ ] Every other supported locale resolves every GameWorld extension key from a locale-specific translated overlay before English fallback.
- [ ] TypeScript enforces locale and key completeness for the overlay.
- [ ] existing pre-extension Assistant translations remain unchanged unless their meaning must expand for the new product scope.
- [ ] RTL behavior remains inherited from the existing locale definition; the overlay adds no direction-specific markup.
- [ ] frontend type/build/localization checks are green before `AA-GW-010` is complete.
