# Factual Governor Progression — Source and Completeness Inventory

Status: Active implementation source of truth
Release cutoff: 2026-08-23
Canonical contract: [Factual Governor Progression](factual-governor-progression.md)

This inventory turns source discovery into an explicit implementation ledger. A listed source is not considered delivered merely because it is linked here: the reusable factual rows must exist in a reproducible immutable release, or the row/family must carry a concrete conflict, source-gap, licensing, or schema disposition.

## Canonicalization rule for open confirmed data

Complete openly published factual tables are implementation input, not future research. When the table is inspectable, its scope/units are representable, its provenance can be pinned, and reuse/normalization is compatible with the documented source policy, the importer copies the factual values into the immutable release and records attribution/source locks.

Community ownership alone is not a reason to leave a complete table `indexed_external_table`.

The importer must still:

- omit recommendation, tier-list, optimizer, investment-priority, ranking-opinion and similar advisory fields;
- preserve factual concepts that happen to use words such as tier, rank, score, rally or garrison;
- keep missing cells unknown;
- preserve conflicts and source gaps explicitly;
- pin source content with SHA-256 or immutable upstream revision where obtainable;
- never scrape third-party sources during application requests;
- keep calculator eligibility separate from reference eligibility.

## Confirmed maintained source inventory

### Century Games / KingShot official material

Authority: Tier A.

Use official site/wiki/support/game evidence for terminology, first-party unlock behavior, version boundaries and reconciliation where available. Normalize factual content; do not wholesale-copy expressive guide prose or third-party images.

### Century Games official Governor Gear / Charm tables

The selected official KingShot wiki Governor Gear page exposes a complete 58-row Governor Gear ladder and a complete 22-level Governor Charm ladder. Release `2026.08.23.2` uses those Tier-A rows as canonical current Gear/Charm facts where they overlap community data. Ten Governor Gear rows differed from the earlier open structured community feed; the official values are canonical and every displaced community row is retained as an explicit `superseded_by_tier_a` claim. The older community claim that Governor Charms stop at level 21 remains historical provenance; the current 22-level boundary is resolved by the official table plus the maintained KR table.

Because the official page contains dynamic non-factual HTML, its immutable source snapshot is the SHA-256 of the normalized 58 Gear + 22 Charm factual rows after the expected table structure and row counts are validated. A cosmetic page change therefore does not create false release drift; a factual row change does.

### Kingshot Data KR corroboration

The maintained KR reference contributes independent structured corroboration for all 22 Governor Charm levels and the 10-level Exclusive Hero Gear Widget ladder (275 Widgets total). These pages are likewise pinned by normalized factual-table SHA-256 rather than volatile page HTML.

### Kingshot Data

Authority: Tier B maintained structured community reference.
Canonical source root: `https://kingshotdata.com/`

Confirmed release-cutoff coverage:

| Family/source surface | Confirmed coverage | Canonicalization disposition |
| --- | ---: | --- |
| Heroes category | 34 Hero identities across 7 generations plus Rare/Epic Heroes | Import factual Hero-page tables; retain the 34-identity canonical roster. |
| Buildings category | 12 maintained Building pages | Import every factual level/requirements/cost/time/power/capacity/unlock table exposed by the pages. |
| Pets category | 14 Pet pages | Import factual level/advancement/refinement/skill/material tables wherever published; identity remains canonical when a detail field is absent. |
| Masters category | 6 Masters | Import factual Affinity, skill-level, power, Learning XP, Manuscript and special-research/talent tables wherever published. |
| Database & Charts category | 8 maintained chart pages | Import/retain factual Governor Gear, Governor Charm, Hero Gear Enhancement, Hero Shards, Mastery Forging, Max Levels, Server Timeline and Widgets tables as corroborating structured facts. |
| Academy Research | 191 technologies across Development 45 / Economy 44 / Battle 102 | Import all technology identities and all visible level rows. |
| Academy level rows | 714 visible rows | Import all 714 rows exactly as visible. Do not infer the six absent rows described below. |
| Alliance Tech | 60 technologies across Growth 24 / Territory 16 / Battle 20 | Import all technology identities and all 279 visible levels including alliance research cost, donation, bar, time, effect and requirement fields. |
| Alliance Tech level rows | 279 visible rows | Import all 279 rows and graph-check requirements where they resolve to stable technologies. |
| Events category | 33 maintained Event pages at the cutoff | Import only structured factual progression/scoring tables and values; narrative strategy remains non-canonical. |
| Max Levels reference | current maintained caps as of 2026-07-23 | Treat as a sourced summary/corroboration layer, not a substitute for finer level tables. |

### Verified Academy source gap

Kingshot Data publishes **191 Academy technology identities** and their declared max levels. Exactly **190** technologies expose per-level tables containing **714 visible rows**. `Fortified Mail VI` is the single technology that declares `Max Level 6` but exposes no per-level table at the release cutoff.

Canonical treatment:

- canonicalize `Fortified Mail VI` identity, tree and declared max level 6;
- store `levels=[]` with an explicit `source_table_missing` state;
- record six missing visible level rows as a source gap;
- do not synthesize values from neighboring tiers/patterns;
- a future source table or independently verified in-game evidence creates a new immutable release.

### KingshotPro open structured data

Authority: Tier B/C depending on dataset provenance.
Canonical source root: `https://kingshotpro.com/data/`
Reuse note: structured feed declares CC-BY-4.0 metadata; attribution/source metadata is retained.

Selected factual feeds for this release:

- Governor Gear;
- Buildings/core progression;
- Masters structured feed;
- Troops;
- Truegold;
- War Academy;
- Governor Charms;
- Hero XP;
- Hero shards;
- VIP;
- Kingdom of Power/KvK factual scoring.

Canonical treatment:

- copy structured factual fields and source metadata;
- preserve source confidence/version metadata at the finest available scope;
- strip only explicitly advisory fields such as recommendations, tier-list/ranking opinion and optimizer output;
- use maintained independent tables as reconciliation/corroboration where the same scoped fact is exposed elsewhere;
- do not weaken calculator evidence requirements.

### g2384/Kingshot-Data GitHub dataset

Authority: Tier C inspectable community dataset.
Pinned upstream content is used for structured Hero facts such as identities, rarity/generation/class, acquisition metadata, stats, skill names and structured skill/effect ladders where inspectable.

Canonical treatment:

- pin the exact upstream commit/blob used by the release;
- import structured factual values only;
- omit descriptive guide/strategy prose and recommended lineup content;
- treat lineup strings/rankings as excluded strategy opinion;
- reconcile Hero identity against the canonical 34-Hero roster rather than creating duplicate identities.

### Community formation evidence

Authority: Tier D community convention evidence.

Repeated formation ratios may be stored only as source-scoped conventions with mode/context, aliases, source IDs and disagreement notes. They are never authoritative game rules and cannot carry recommendation scores, `best` flags or optimizer output.

## Source adapter contract

Source adapters are release tooling, never runtime dependencies.

Each adapter must:

1. fetch the documented canonical source URI;
2. fail on transport errors rather than silently publish an empty family;
3. validate known release-cutoff entity/row counts where the source itself declares them;
4. record SHA-256 for the fetched hub/feed and each retained factual detail page;
5. parse structured tables/JSON facts only;
6. reject unexpected structural drift for known-complete datasets;
7. write normalized candidate facts with source IDs and source locators;
8. retain explicit source gaps instead of filling them;
9. strip advisory/recommendation fields without stripping factual `tier`, `rank`, `score`, role or event fields;
10. produce deterministic semantic output from identical source snapshots.

## Release-cutoff hard completeness assertions

The `2026.08.23.2` immutable candidate must not publish unless all of these are true:

- canonical Hero roster contains exactly 34 discovered Heroes;
- Academy contains exactly 191 technology identities;
- Academy contains exactly 714 visible per-level rows;
- Academy contains exactly one documented source-table gap: `Fortified Mail VI`, declared max 6, six absent rows;
- Alliance Tech contains exactly 60 technology identities and 279 visible level rows;
- Building source sweep discovers exactly 12 maintained Building detail pages;
- Pet source sweep discovers exactly 14 maintained Pet detail pages;
- Master source sweep discovers exactly 6 maintained Master detail pages;
- Database & Charts source sweep discovers exactly 8 maintained chart/detail pages;
- Hero source sweep discovers exactly 34 maintained Hero detail pages;
- the selected open Governor Gear and War Academy feeds match their pinned source-declared counts;
- all generated files are declared in the immutable release manifest and included in the release checksum;
- no `indexed_external_table` disposition remains for a reusable complete table represented by these selected sources;
- advisory fields are absent from the canonical release;
- every referenced source ID exists in the source registry;
- source-lock SHA-256 values are valid and non-empty;
- known conflicts/source gaps are non-zero only when explicitly represented in the release report.

A count changing upstream is not automatically an error in the product. It is a release-review event: the adapter fails closed, the source sweep is repeated, `/docs/product` is reconciled with the newly discovered family/entity count, and only then is a new immutable release created.

## Semantic family dispositions for `2026.08.23.2`

| Family | Required release state |
| --- | --- |
| Heroes | Canonicalized, including current identity roster and inspectable structured skill/stat/acquisition facts. |
| Hero XP / shards | Canonicalized from open structured ladders and corroborating charts. |
| Hero exclusive equipment / Widgets | Canonicalized to every inspectable row; genuinely unpublished per-Hero effects remain explicit unknowns. |
| Hero Gear / Mastery | Canonicalized to inspectable enhancement/mastery/material rows. |
| Governor Gear | Canonicalized to the full selected per-step factual ladder with confidence/provenance. |
| Governor Charms | Canonicalized to the full selected per-level factual ladder while retaining historical max-level conflict history. |
| Named formations | `community_convention`, never recommendation truth. |
| Buildings / unlocks | Canonicalized from the complete 12-page maintained Building sweep plus structured open feeds. |
| Troops | Canonicalized from the complete selected structured troop feed and Building unlock tables. |
| Academy Research | 191 identities + 714 visible rows + one explicit six-row source gap. |
| War Academy | Canonicalized from the complete selected open technology feed and maintained Building/War Academy facts. |
| Alliance Technology | 60 identities + 279 visible rows, canonicalized. |
| Pets | 14 identities plus every published factual table; missing unpublished fields remain unknown. |
| Masters | 6 identities plus every published factual Affinity/skill/material/research table. |
| Truegold | Canonicalized structured item/building progression facts. |
| VIP | Canonicalized factual progression rows only. |
| Progression Event scoring | Canonicalize structured factual scoring rows; exclude narrative strategy. |
| Max levels/server timeline | Canonicalized summary/corroboration with source date/version boundaries. |

## Completion rule

This source inventory is complete for the release cutoff only when every selected source surface above is either reproduced in the immutable release or has a concrete row/family disposition that explains why reproduction is impossible without guessing or violating the source policy. "Another site has the table" is not a valid completion state. Conversely, "community-maintained" is not a valid reason to refuse a complete open/inspectable factual table.
