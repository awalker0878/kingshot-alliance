# Factual Governor Progression

Status: Active delivery — 2026-08-23

## Product outcome

Factual Governor Progression gives Governors and Alliance officers a trustworthy, inspectable KingShot progression reference and a factual view of an observed Governor roster without turning community advice into authoritative game rules.

The capability has three deliberately separate concepts:

1. **KingShot progression catalogue truth** — immutable, versioned factual reference data owned by `GameWorld/Progression`.
2. **Observed Governor progression** — dated, source-labelled observations owned by `Intelligence/Roster`; an observation is evidence about a Governor, not catalogue truth.
3. **Saved tactical intent** — named Hero/formation loadouts owned by the applicable Operations planning capability; a saved loadout is a plan, not an observation and not a recommendation.

The first delivery objective is data completeness and trustworthiness. Hero recommendations, tier lists, personalized upgrade advice, optimization, cost calculators and inferred combat formulas are explicitly not part of this capability.

## Non-negotiable trust rules

1. Missing means `unknown`; it is never silently converted to zero, interpolated or inferred from a pattern.
2. A community calculator result is not a source table.
3. A community strategy statement is not a game rule.
4. A source changing later never rewrites a published dataset release.
5. Every canonical fact is traceable to one or more source records and a concrete immutable dataset release.
6. Every source record has a source URI, source label, retrieval timestamp, observation/version boundary when available, locale, content checksum and usage/licensing note.
7. Numeric facts intended to unlock a calculator remain subject to the existing calculator evidence gate even when they are useful as clearly-labelled reference data.
8. Conflicting source values are preserved as a conflict. The importer must not pick whichever value was seen last.
9. Community formations are stored as sourced, scoped **community conventions**. They are never labelled `best`, `optimal` or authoritative unless an official inspectable game source explicitly establishes such a rule.
10. Raw external pages are discovery/evidence inputs. Runtime product behavior uses committed/reviewed canonical releases; production requests do not scrape community sites.

## Architectural ownership

### GameWorld/Progression

`GameWorld/Progression` owns:

- source registry records used to establish game-reference provenance;
- immutable source observations/snapshots or manifests where copying the raw work is permitted;
- immutable dataset releases and checksums;
- canonical KingShot reference entities, facts and relationships;
- reconciliation outcomes and explicit conflicts;
- source-backed community convention records such as named formations;
- dataset coverage/completeness reports.

It does **not** own a Governor's observed roster, Alliance membership, Event roster assignments or saved tactical intent.

### Intelligence/Roster

`Intelligence/Roster` continues to own append-only Governor observations. Normalized Hero/loadout-related observations may reference stable progression catalogue identifiers, but the observation keeps its own `observed_at`, source and provenance. Deleting, correcting or superseding a catalogue source must not silently rewrite a historical Governor observation.

### Operations planning

Saved loadouts/formations are user planning intent and remain separate from both catalogue facts and observations. They may reference immutable catalogue entities and a dataset release. They cannot claim that the application recommended the selected Heroes or formation.

### Read composition

Cross-owner pages may compose catalogue data, Governor observations and saved planning intent through `app/ReadModels`. A read model never becomes a persistence owner.

## Dataset taxonomy

The canonical corpus must support all discovered progression families. The initial required families are:

### Heroes

- Hero identity, canonical name and aliases/translations where sourced;
- rarity;
- troop class/type;
- generation;
- release/server-age boundary where sourced;
- acquisition/unlock methods;
- Hero level and star progression;
- shard requirements and shard restrictions;
- level/stat progression where inspectable;
- Expedition/Conquest or equivalent supported stat/effect facts;
- skills, skill slots/types and level-by-level sourced effects;
- exclusive Hero equipment and Widget progression;
- Widget costs, effects and skill/effect unlocks.

### Hero Gear

- gear slot/type and troop-class applicability;
- rarity/quality;
- enhancement levels and enhancement XP where sourced;
- equipment XP values where sourced;
- stat/power effects;
- Mythic/Legendary/Red progression where sourced;
- Mithril or other material requirements;
- Mastery Forge levels, costs, requirements and effects;
- reforge/refund behavior only where inspectably sourced.

### Governor Gear

- all equipment slots;
- troop-type association;
- quality/tier/level/star progression as represented by the game/source;
- material requirements;
- stat/power effects;
- set thresholds and sourced set bonuses.

### Governor Charms

- charm family/slot and troop association;
- level/tier progression;
- material requirements;
- stat/power effects;
- explicit conflicts where maintained sources disagree on max level or costs.

### Named Formations

- stable internal identifier and descriptive canonical label;
- sourced aliases where a community uses a name;
- Infantry/Cavalry/Archer percentages;
- mode/use scope, role and generation/server-age scope where stated;
- required/compatible Hero references only when the source actually makes that claim;
- source and evidence status;
- community notes/disagreement;
- no optimization score and no `best` flag.

Formation percentages must sum to 100. Conflicting strategic claims may coexist because they are conventions, not a single authoritative rule.

### Buildings and unlocks

- building identity;
- level/sublevel/Truegold stage where applicable;
- prerequisite buildings/levels;
- resource/material costs;
- base upgrade duration;
- power/effects/capacity;
- feature unlocks;
- Town Center or other gate requirements;
- Truegold and Tempered Truegold progression where sourced.

### Troops

- Infantry/Cavalry/Archer identity;
- troop tier;
- training-building and building-level unlock;
- attack/defense/health/lethality/power where inspectable;
- training/promotion costs and duration where inspectable;
- training capacity/speed relationships where sourced;
- T11/Truegold requirements where sourced.

### Academy research

- research tree/section;
- technology identity;
- every sourced level;
- effect/value/unit;
- resource costs;
- base duration;
- power;
- Academy requirement;
- technology prerequisite graph.

### War Academy research

War Academy data follows the same level/fact/dependency model as Academy research and retains its own unlock/version/server-age boundaries.

### Pets

- Pet identity, generation/release boundary and rarity where sourced;
- level/advancement/refinement;
- skills and skill-level effects;
- advancement/taming/material requirements;
- unlock conditions.

### Masters

- Master identity and release boundary;
- Affinity progression;
- talents;
- skills and skill-level effects;
- Master Academy/Special Research where represented by the source;
- manuscripts/learning XP/material requirements.

### Additional discovered families

Source discovery is part of delivery. The sweep must also disposition factual progression material for, where available:

- Alliance Technology;
- Truegold/Tempered Truegold systems;
- max-level caps and server-age unlocks;
- deployment/rally capacity;
- Infirmary/Storehouse/capacity progression;
- recruitment/unlock pools;
- VIP progression;
- Watchtower/Intel progression;
- Beast/Terror level caps;
- Bear Pitfall progression;
- recurring progression-scoring tables;
- materials/items and their factual progression use;
- any other structured KingShot progression family discovered during the research sweep.

A newly discovered family is added to this contract and delivery ledger before implementation. It cannot be silently deferred as a future enhancement.

## Source registry

Each source record contains at minimum:

- stable source key;
- publisher/maintainer;
- source label;
- canonical URI;
- source kind;
- authority tier;
- official/first-party indicator;
- locale;
- `retrieved_at`;
- `observed_at` or publication/update date where known;
- game-version/server-age/generation applicability where known;
- content checksum or immutable upstream commit/blob checksum where possible;
- license/usage note;
- source-specific locator for a fact when practical;
- notes about access limitations, opacity or known conflicts.

### Authority tiers

**Tier A — first-party/official**

- Century Games / KingShot official wiki or support material;
- official patch notes/announcements;
- reviewed in-game evidence with immutable evidence provenance.

**Tier B — maintained structured community reference**

- maintained KingShot data/wiki sites exposing inspectable tables or per-level facts.

**Tier C — inspectable community/open-source dataset**

- GitHub or equivalent repositories where the underlying structured values and revision can be inspected.

**Tier D — community observation/discussion**

- Reddit, Discord, guides and similar discussion material.

Tier D is useful for discovery and conventions. It does not by itself satisfy the evidence threshold for an authoritative numeric game table.

## Evidence status

Canonical facts/conventions use explicit status values equivalent to:

- `official`;
- `corroborated`;
- `single_source`;
- `community_convention`;
- `observed_in_game`;
- `conflicting`;
- `superseded`;
- `unknown`.

The UI must not collapse these into a generic `verified` badge.

## Versioning and immutability

A dataset release has:

- stable release ID;
- schema version;
- human-readable dataset version;
- generated/reviewed timestamps;
- complete source manifest;
- release checksum;
- per-family record counts;
- conflict and unresolved counts;
- coverage report;
- release notes.

Published releases are immutable. A changed source, parser, normalization rule or fact produces a new release. Historical releases remain queryable/reproducible for facts pinned by saved planning intent or historical observations.

The checksum is calculated from a deterministic canonical representation. Re-importing identical canonical input must produce the same semantic release content and must not duplicate entities/facts.

## Reconciliation rules

1. Normalize source-specific names into candidates before canonicalization; preserve source spelling/aliases.
2. Reconcile by stable canonical identity and explicit source locator, never fuzzy-name overwrite.
3. Exact agreement across independent sources may increase confidence; the provenance links remain independent.
4. If values disagree and no higher-authority inspectable source resolves them, publish an explicit conflict rather than a chosen value.
5. A first-party source may establish an official fact for the version/scope it actually covers; it does not automatically invalidate a community-observed value for a different version/server age.
6. Units must be explicit before numeric values can be compared.
7. Percentages/rates use one documented canonical representation; importers normalize source presentation without changing meaning.
8. Durations retain base-duration semantics when a source gives base time. Buffed/player-specific times are not canonical game cost facts.
9. Cumulative totals are derived only when all component levels needed for the sum are present in the same compatible dataset scope. Derived totals are marked derived and traceable to component facts.
10. Research/build prerequisite graphs must resolve to canonical nodes; unresolved dependencies block a `complete` family disposition.
11. A conflict cannot be hidden merely to satisfy a calculator prerequisite.

## Existing calculator evidence gate

This capability does not weaken the existing calculator gate.

A factual table can be useful in the reference UI while still being insufficient to power a calculator. Calculator-target numeric rows require the source URI, source label, `observed_at`, version boundary and unit already required by the global delivery ledger. They must also satisfy the existing reconciliation threshold: one official inspectable table, or the independently corroborated evidence package required by that ledger.

Until a family passes that gate, downstream calculator status remains `Evidence-gated`.

## Canonical storage/import contract

The repository stores reviewed import manifests/canonical source material needed to reproduce a release. Runtime requests never depend on live scraping.

The ingestion pipeline is conceptually:

```text
source registry
    -> immutable retrieval/manifest
    -> source-specific parser/normalizer
    -> normalized candidates
    -> schema + unit validation
    -> reconciliation
    -> conflict/coverage report
    -> immutable canonical release
```

Import is idempotent. A failed import leaves no partially published release. Release publication occurs only after validation/reconciliation completes.

## Governor roster observation contract

A normalized Governor Hero observation can include, when actually observed:

- Hero catalogue ID;
- observed Hero level;
- star/substar/tier;
- skill levels;
- observed Hero Gear slots/levels;
- Mastery levels;
- exclusive equipment/Widget level;
- `observed_at`;
- source/provenance;
- confidence or review state.

The observation is append-only history. Absence of a Hero in one observation does not prove that the Governor does not own it unless the source explicitly establishes a complete roster capture.

Free-text progression observations remain supported for evidence that cannot yet be normalized safely.

## Saved loadout contract

A saved loadout is factual user-entered planning intent with:

- name;
- optional Event/mode/purpose label;
- three Hero references where the represented game mode uses three;
- optional referenced named formation or explicit troop percentages;
- pinned/recorded dataset release;
- user notes;
- ownership/scope;
- created/updated attribution.

The application may validate identity and percentages. It must not turn a saved loadout into an implicit recommendation score.

## Authorization

### Read

Authenticated, email-verified users with an active Player may browse the factual progression catalogue. Alliance-specific Governor observations and loadouts additionally require the current concrete Alliance/Player access already defined by their owning capabilities.

### Catalogue mutation

End users do not directly edit canonical game facts. Dataset import/reconciliation/publication occurs through controlled system/platform-maintenance boundaries with explicit audit provenance.

### Governor observations

Recording/correcting normalized Governor observations uses the existing `Intelligence/Roster` authority model and revalidates current Player/Alliance scope at the write boundary.

### Loadouts

A Governor may manage their own permitted loadouts; Alliance-owned/shared loadouts require the owning Operations permission. Server authorization remains authoritative regardless of frontend visibility.

## UX contract

### Progression Library

The primary factual surface provides:

- search;
- family filters;
- generation/troop-class/version filters where meaningful;
- bounded pagination;
- explicit unknown/conflict states;
- source/evidence badge text;
- dataset version and last-reviewed context.

### Entity detail

Each entity detail shows factual progression tables/relationships and a provenance panel containing the source label, observation/version boundary and evidence status. A conflicting value shows the competing sourced claims rather than one silently selected answer.

### Heroes

Hero details expose factual identity/class/generation/acquisition, progression, skills, exclusive equipment/Widgets and applicable Hero Gear relationships where present in the release.

### Formations

Formation cards show the troop ratio, use/mode scope, source and explicit `Community convention` wording. Strategy disagreement is visible. The page contains no ranking, score or `recommended` sort.

### Governor Progression

Governor history can display normalized Hero observations alongside existing observed power/progression facts without changing its append-only semantics. Missing values render as unknown/not observed, never zero.

### Loadouts

Loadouts provide accessible form controls for Hero selection and troop percentages, expose the pinned dataset version and clearly say that the saved composition is user planning intent.

### Required states

Desktop and mobile UX must cover:

- loading/busy;
- no dataset published;
- no results;
- complete factual result;
- single-source result;
- unknown value;
- source conflict;
- superseded release;
- stale/old source observation where applicable;
- permission denied for owner-specific data;
- invalid import/reconciliation diagnostics for authorized maintainers.

All material controls are keyboard usable, semantically labelled, localized through the repository localization system and covered by deterministic visual regression where the repository requires it.

## Completeness and conflict reporting

Every release emits a machine-readable per-family report containing at least:

- entities discovered;
- entities canonicalized;
- facts imported;
- corroborated facts;
- single-source facts;
- community conventions;
- conflicts;
- unresolved references;
- explicitly excluded/unverifiable source items;
- source coverage.

A family cannot be marked complete while a discovered structured entity is silently absent. It must be canonicalized or have an explicit disposition.

## Acceptance criteria

### AC-1 — Product/architecture truth

`/docs/product`, architecture ownership, reference docs, permission docs and implementation agree that GameWorld owns catalogue truth, Intelligence owns Governor observations and Operations owns planning intent.

### AC-2 — Source provenance

Every published canonical fact/convention traces to a registered source and immutable release. Required source/version/unit metadata is enforced by tests/schema validation.

### AC-3 — Reproducible releases

The same reviewed canonical input produces deterministic content/checksum and an idempotent import. Changed semantic input produces a new immutable release.

### AC-4 — Conflict safety

Contradictory source values remain explicit and cannot be silently promoted to authoritative truth or a calculator table.

### AC-5 — Hero completeness

The research sweep has dispositioned every discovered Hero and factual Hero skill/progression/exclusive-equipment family; the factual UI exposes the canonicalized portion with provenance.

### AC-6 — Gear/Charm completeness

Hero Gear, Mastery Forge, Governor Gear and Governor Charm source families are canonicalized or explicitly conflict/exclusion-dispositioned at the finest level made inspectable by the sources.

### AC-7 — Formation honesty

Named troop formations are source-scoped conventions, percentages sum to 100 and no product/API field claims an optimal or recommended formation.

### AC-8 — Buildings/troops completeness

Discovered building, unlock, resource/time/power and troop-tier progression tables are represented or explicitly dispositioned, with resolvable prerequisite/unlock relationships where supplied.

### AC-9 — Research completeness

Academy and War Academy technologies/levels and dependencies exposed by the selected inspectable sources are represented or explicitly dispositioned; dependency validation reports unresolved nodes/cycles.

### AC-10 — Pets/Masters/additional families

Pets, Masters and every additional factual progression family discovered in the sweep have canonical data or an explicit conflict/exclusion disposition.

### AC-11 — Governor observation separation

Normalized Hero observations extend append-only Roster history without making catalogue rows Player-owned or treating a missing observation as zero/non-ownership.

### AC-12 — Loadout separation

Saved loadouts are independently persisted planning intent, pin/reference dataset identity and are not stored as Roster observations or catalogue facts.

### AC-13 — Factual UX

Authorized users can search/browse detail/provenance/conflict states on mobile/desktop with accessibility/localization parity. The UX contains no recommendation/tier/optimization behavior.

### AC-14 — Calculator gate remains closed by default

No calculator is introduced by this delivery. A separate later change may unlock only the individual data family proven to satisfy the pre-existing calculator gate.

### AC-15 — Completeness proof

The release coverage report has no silently missing discovered structured entities; all unresolved/conflicting/excluded items are enumerated with reason and source.

### AC-16 — Repository Definition of Done

Behavior, authorization, idempotency, audit/observability, responsive UX, accessibility, localization, tests, deterministic visual coverage and applicable repository release gates pass on one immutable implementation candidate before closeout.

## Delivery ledger

`Complete` requires the full exit condition, not scaffolding or a partial data sample.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Product contract | This canonical `/docs/product` contract defines outcome, taxonomy, evidence/reconciliation, ownership, UX, authorization, acceptance criteria and the non-bypassable calculator gate. |
| 1 | In progress | Architecture + source/release foundation | Capability/data ownership docs, source registry, immutable releases, canonical schema, deterministic checksum, idempotent publish and behavior tests are complete. |
| 2 | In progress | Source discovery + evidence inventory | Official/community wiki/database/open-source/community sources are systematically surveyed; source manifests and explicit source/family dispositions exist. |
| 3 | In progress | Heroes | Complete discovered Hero roster, identity, class/rarity/generation/acquisition, progression, shards, skills and aliases are canonicalized/dispositioned. |
| 4 | In progress | Exclusive equipment + Widgets | Discovered exclusive Hero equipment/Widget levels, costs/effects/unlocks are canonicalized/dispositioned. |
| 5 | In progress | Hero Gear + Mastery | Discovered Hero Gear enhancement/rarity/material/stat facts and Mastery Forge progression are canonicalized/dispositioned. |
| 6 | In progress | Governor Gear + Charms | Slots/classes, tiers/levels, materials, effects/set bonuses and Charm conflicts are canonicalized/dispositioned. |
| 7 | In progress | Named formations | Repeatedly documented troop-ratio conventions are source-scoped, mode-scoped, conflict-aware and explicitly non-recommendation. |
| 8 | In progress | Buildings + unlocks | Discovered building levels, prerequisites, costs/times/power/capacities/unlocks and Truegold progression are canonicalized/dispositioned. |
| 9 | In progress | Troops | Discovered troop tiers, stats, unlocks, training/promotion facts and T11/Truegold requirements are canonicalized/dispositioned. |
| 10 | In progress | Academy + War Academy | Discovered technology levels/effects/costs/times/power/prerequisites are canonicalized/dispositioned and graph-validated. |
| 11 | In progress | Pets + Masters | Discovered Pet and Master progression/skills/materials/Affinity/talent/research facts are canonicalized/dispositioned. |
| 12 | In progress | Additional progression families | Alliance Tech, max-level/server-age, capacity, VIP/Watchtower/Beast/Terror/Bear Pitfall/material and every other discovered structured progression family is canonicalized or explicitly dispositioned. |
| 13 | In progress | Governor Hero observations | Roster history can retain normalized sourced Hero/loadout-adjacent observations without weakening append-only/provenance semantics. |
| 14 | In progress | Saved loadouts | Separate authorized user/Alliance planning intent references catalogue Heroes/formations/dataset release without recommendation semantics. |
| 15 | In progress | Factual Progression Library UX | Search, filters, entity details, provenance, conflict/unknown states, formations, Governor history integration and loadouts are responsive/accessibility/localization complete. |
| 16 | In progress | Completeness/reconciliation gates | Machine-readable coverage/conflict reports, schema/unit/reference checks, idempotency/checksum tests and no-silent-omission enforcement are green. |
| 17 | In progress | Final reconciliation + release gates | Spec→code, code→spec, data→source, source→disposition, UX→backend, authorization and ownership scans find no unimplemented requirement; all applicable repository gates pass on one immutable candidate. |

After each phase or requirement is completed, implementation proceeds directly to the next incomplete row. A discovered correctness, data, UX, authorization, observability or architectural requirement is added here and implemented before closeout.

## Closeout rule

This capability is not complete because schemas exist or because a representative Hero subset renders. It is complete only when the public progression source surface has been systematically surveyed, all discovered relevant structured data is canonicalized or explicitly dispositioned, all required factual UX and ownership boundaries are implemented, completeness/conflict reports are green, the calculator gate remains intact, and one immutable candidate passes the repository Definition of Done.
