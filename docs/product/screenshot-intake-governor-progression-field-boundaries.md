# Screenshot Intake: Governor Progression — compound field boundaries

Status: Contract amendment — must be consolidated into `screenshot-intake-governor-progression.md` before release closeout.

This amendment is part of the implementation source of truth for Screenshot Intake: Governor Progression.

## Gear field separation

For `governor_hero_gear` and `governor_gear`, a single OCR line may contain several adjacent facts. The extractor and fixture corpus must treat these as separate fields rather than allowing one candidate to consume labels or values belonging to another fact.

- `gear_quality` contains only the visible quality/tier value.
- `gear_level` contains only the visible gear level.
- `mastery_level` contains only the visible Hero Gear mastery value.
- `gear_star` contains only the visible Governor Gear star value.
- A quality/tier candidate terminates before a following `Level`/`Lv`, `Mastery`/`Mastery Forge`, `Star`/`Stars`, or end-of-line boundary.
- Adjacent field labels and their numeric values remain retained in raw OCR provenance, but they are not part of the normalized quality candidate.

Fixture expectations must assert the separated semantic values (for example `Mythic`, `100`, `10`) rather than encoding an accidental regex span such as `Mythic Level 100 Mastery 10` as one quality fact.

This rule applies symmetrically to Hero Gear and Governor Gear extraction and does not add any new supported screenshot field.