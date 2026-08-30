# Alliance Assistant GameWorld extension — UX reconciliation amendment

Status: Current delivered amendment — 2026-08-30

This file is a normative amendment to `docs/product/alliance-assistant-gameworld-extension.md` discovered during visual verification. It does not create a second delivery ledger; `AA-GW-010`, `AA-GW-011`, and `AA-GW-013` in the primary contract remain authoritative and Pending until verified.

## First-use discovery compatibility

The GameWorld extension **adds** bounded capabilities to the established Alliance Assistant discovery surface. It must not make the existing Event/guide/observation capabilities undiscoverable merely to keep the prompt grid small.

The zero-turn first-use state must expose all nine closed prompt identifiers currently supported by the extension:

- `swordland_roster`;
- `next_event`;
- `bear_hunt_guide`;
- `observation`;
- `hero_fact`;
- `rsvp_week`;
- `battle_assignment`;
- `transfer_status`;
- `territory_plan`.

The established four prompts remain first in reading/focus order. Extension prompts follow them. The grid must wrap without horizontal overflow at the repository's desktop and mobile visual-regression viewports.

When an answer returns a bounded `suggestedQuestions` set, the client may render at most the nine declared prompt identifiers above; unknown prompt identifiers remain ignored.

### Acceptance criteria

- [ ] desktop first-use state contains the established four prompts and all five GameWorld extension prompts;
- [ ] mobile first-use state contains the same prompt set with no horizontal overflow;
- [ ] German visual coverage continues to prove the established localized observation prompt is present;
- [ ] prompt buttons remain ordinary keyboard-focusable buttons and preserve deterministic closed prompt IDs;
- [ ] visual regression is updated only after the behavioral assertions pass; screenshot hashes are evidence of the accepted state, not a substitute for those assertions.
