# Screenshot Intake: Governor Progression — pinned catalogue validation

Status: Contract amendment — must be consolidated into `screenshot-intake-governor-progression.md` before release closeout.

This amendment is part of the implementation source of truth for Screenshot Intake: Governor Progression.

## Catalogue-backed facts versus screen-local structure

The reviewed Roster payload must validate every fact against the pinned `GameWorld/Progression` release to the extent that release exposes authoritative meaning. It must not manufacture canonical identities for structural labels that the catalogue does not model as identities.

- Canonical Hero identity must resolve in the pinned release.
- Hero level, star/substar and Widget bounds remain constrained by the pinned progression model/schema.
- Hero Gear `slot_id` is a closed screen-local structural key, not a `GameWorld/Progression` entity ID unless a future dataset explicitly publishes such an identity.
- Governor Gear `slot_id` is likewise a screen-local structural key.
- Governor Charm `slot_id` is likewise a screen-local structural key; v1 does not create a canonical Charm identity because the current pinned release publishes the Charm level ladder, not Charm identity IDs.
- Screen-local keys are retained as reviewed observation structure and must never be written back into `GameWorld/Progression`.

## Hero Gear factual bounds

For the current schema-v2 Progression release, the pinned `database_tables` catalogue publishes the Hero Gear enhancement ladder through level 200 and the Mastery Forging ladder through level 20. Therefore:

- reviewed Hero Gear `level`, when present, must be within the maximum enhancement level actually published by the pinned dataset;
- reviewed `mastery_level`, when present, must be within the maximum Mastery Forging level actually published by the pinned dataset;
- the validator derives these maxima from the pinned dataset rather than embedding a future-facing guess such as 1000;
- if a pinned dataset does not expose the required table/bound, that field fails closed rather than silently accepting an arbitrary upper bound.

## Governor Gear factual meaning

The current pinned `governor_gear` catalogue publishes tier/star upgrade steps. `quality`/tier and `star`, when reviewed, must not be described as canonical unless their meaning can be reconciled with a published pinned step. A directly visible numeric `level` may remain an observation when the screenshot schema proves it, but it is not promoted into a canonical Progression fact merely because a number was visible.

Validation must preserve the distinction between a directly observed screen value and a canonical catalogue identity/fact. Future dataset additions may strengthen validation only through an explicit schema/product change; historical Evidence remains pinned to its original release.
