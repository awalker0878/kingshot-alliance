# Screenshot Intake: Governor Progression — classification boundaries

Status: Contract amendment — must be consolidated into `screenshot-intake-governor-progression.md` before release closeout.

This amendment is part of the implementation source of truth for Screenshot Intake: Governor Progression.

## Governor Charms fail-closed signature

The `governor_charms` classifier must distinguish the supported Governor/Chief Charm progression screen from unrelated Charm collection, inventory, statistics or summary UI.

- An explicit `Governor Charm(s)` or `Chief Charm(s)` screen label is a strong class signature.
- The generic token `Charm`/`Charms` by itself is not sufficient to classify a screenshot as `governor_charms`.
- Generic screens such as `Charms Collection` remain `unknown` unless other fixture-backed Governor progression structure independently reaches the supported classification threshold.
- A supported screenshot may still classify as `governor_charms` when a required extraction field is missing; classification identifies the screen family, while extraction/review separately determines whether commit-ready meaning exists.
- The user-selected expected kind remains a hint only and must not promote a generic or unsupported Charm screen into the supported family.

Classifier implementation/version provenance must change when this class-signature behavior changes.