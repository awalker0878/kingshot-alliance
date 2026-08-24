# Factual Governor Progression

Status: Active delivery — 2026-08-23

## Product outcome

Factual Governor Progression gives Governors and Alliance officers a trustworthy, inspectable KingShot progression reference and a factual view of observed Governor progression without turning strategy advice into authoritative game rules.

The capability deliberately keeps three concepts separate:

1. **KingShot progression catalogue truth** — immutable, versioned factual reference data owned by `GameWorld/Progression`.
2. **Observed Governor progression** — dated, source-labelled observations owned by `Intelligence/Roster`; an observation is evidence about a Governor, not catalogue truth.
3. **Saved tactical intent** — named Hero/formation loadouts owned by the applicable Operations planning capability; a saved loadout is user intent, not an observation and not a recommendation.

The primary delivery objective is data completeness and trustworthiness. Hero recommendations, tier rankings, personalized upgrade advice, optimization, cost calculators and inferred combat formulas are outside this capability.

## Non-negotiable trust rules

1. Missing means `unknown`; it is never silently converted to zero, interpolated or inferred from a pattern.
2. A community calculator result is not a source table.
3. A community strategy statement is not a game rule.
4. A source changing later never rewrites a published dataset release.
5. Every canonical fact is traceable to one or more source records and a concrete immutable dataset release.
6. Every source record carries source URI, source label, retrieval timestamp, observation/version boundary when available, locale, content checksum or immutable upstream revision when obtainable, and usage/licensing notes.
7. Numeric facts that might later power calculators remain subject to the existing calculator evidence gate even when they are accepted into the factual reference corpus.
8. Conflicting source values are preserved as conflicts unless an inspectable higher-confidence/current source resolves the same scoped fact.
9. Community formations are stored as sourced, scoped **community conventions**. They are never labelled `best`, `optimal`, `recommended` or authoritative.
10. Runtime product behavior uses committed reviewed releases; production requests do not scrape community sites.
11. **Open factual tables are data, not merely discovery indexes.** When a maintained source publishes structured factual tables under a reuse-compatible licence, exposes provenance/version/confidence metadata, and the values are inspectable and sufficiently corroborated for factual display, the complete factual table is canonicalized into the immutable release instead of being left `indexed_external_table` solely because the publisher is community-maintained.
12. Recommendation/tier/optimizer fields from an otherwise useful open dataset are excluded at import. Factual fields from that dataset remain eligible for canonicalization.
13. Source confidence is retained at the finest available scope. A dataset may contain verified, corroborated, likely and conflicting rows without collapsing the whole source to one badge.

## Open-source and community-data reuse policy

The corpus may copy, normalize and redistribute factual tables from openly reusable sources when licence terms permit it. The release records attribution and the upstream canonical URI/revision. For example, a CC-BY-4.0 structured source may be snapshotted into `resources/data/progression` with attribution and its source metadata preserved.

A Tier B/C community source can therefore be **canonical factual input** rather than an index-only pointer when all of the following hold:

- the table is structured/inspectable rather than an opaque calculator output;
- the source identifies when or how the values were verified, or another source independently corroborates them;
- units and scope can be represented without guessing;
- reuse is permitted or the imported representation consists of non-expressive factual values that can be safely normalized;
- known source conflicts are retained rather than erased;
- strategy/recommendation fields are excluded from canonical game truth.

This policy does **not** weaken the calculator gate. A table can be complete enough for factual reference and still remain ineligible to drive an automated calculator until the calculator-specific evidence threshold is met.

## Architectural ownership

### GameWorld/Progression

`GameWorld/Progression` owns:

- source registry and source snapshots/manifests;
- immutable dataset releases and checksums;
- canonical KingShot progression entities, facts and relationships;
- reconciliation outcomes, row/fact confidence and explicit conflicts;
- source-backed community convention records such as named formations;
- machine-readable coverage/completeness reports.

It does **not** own a Governor's observed roster, Alliance membership, Event assignments or saved tactical intent.

### Intelligence/Roster

`Intelligence/Roster` continues to own append-only Governor observations. Normalized observations may reference stable progression catalogue identifiers and a pinned dataset release, while retaining their own capture timestamp, source and provenance. Catalogue changes never rewrite historical observations.

### Operations planning

Saved loadouts/formations are planning intent and remain separate from both catalogue facts and observations. They may reference immutable catalogue entities and a dataset release. They cannot claim the application recommended the selected Heroes or troop composition.

### Read composition

Cross-owner pages may compose catalogue data, Governor observations and saved planning intent through `app/ReadModels`. Read models do not become persistence owners.

## Dataset taxonomy

The canonical corpus must disposition every relevant structured progression family discovered during research. A family is not complete merely because its existence is documented.

### Heroes

Canonicalize/disposition:

- identity, canonical name and sourced aliases/translations;
- rarity, troop class/type and generation;
- release/server-age boundary and acquisition/unlock methods where sourced;
- Hero level progression and level-by-level XP/deployment-capacity facts;
- stars/substars, shards and rarity restrictions;
- inspectable stats/effects;
- Expedition/Conquest or equivalent factual effects;
- skills, slots/types and level-by-level sourced effects;
- exclusive Hero equipment/Widgets, levels, costs, effects and unlocks.

### Hero Gear and Mastery Forge

Canonicalize/disposition gear slot/type, troop applicability, rarity/quality, enhancement level/XP, material costs, stat/power effects, Mythic/Red progression, Mithril and other materials, Mastery Forge levels/costs/requirements/effects, and sourced transfer/reforge/refund behavior.

### Governor Gear

Canonicalize all equipment slots, troop-type association, every inspectable tier/star/quality step, material requirements, attack/defense or other sourced effects, power contribution, set thresholds/bonuses and per-row confidence/conflicts.

### Governor Charms

Canonicalize charm family/slot/troop association, every inspectable level/tier, Charm Guide/Design requirements, stat/effect values and power. Conflicting maintained-source max levels or costs remain explicit conflicts unless current corroborated evidence resolves them.

### Named formations

Store stable ID/label, sourced aliases, Infantry/Cavalry/Archer percentages, mode/use/role/generation scope, sourced Hero compatibility where explicitly claimed, source IDs, evidence status and disagreement notes. Percentages must sum to 100. No recommendation score, rank or `best` flag exists.

### Buildings and unlocks

Canonicalize building identity, every inspectable normal/Truegold sublevel, prerequisites, resources/material costs, base duration, power/capacity/effects, feature unlocks, Town Center gates, Truegold and Tempered Truegold costs and current server-age gates.

### Troops

Canonicalize Infantry/Cavalry/Archer (including source synonyms such as Lancer/Marksman) identities, tiers, training-building/level unlocks, inspectable combat stats, power, training/promotion costs/duration, event points when factual, T11/T12/Truegold requirements and applicable server-age boundaries.

### Academy research

Canonicalize each discovered Growth/Economy/Battle technology and every inspectable level with effect/value/unit, resources, base duration, power, Academy requirement and explicit prerequisite graph. Unresolved prerequisites prevent a complete disposition.

### War Academy research

Use the same per-technology/per-level model for Basic/Advanced Truegold research, including Truegold Dust, resources, duration, effects, power, prerequisites, T11/T12 unlock relationships and server-age boundaries.

### Pets

Canonicalize Pet identity, generation/release boundary, rarity where sourced, levels/advancement/refinement, skills/effects, advancement/taming materials and unlock conditions.

### Masters

Canonicalize Master identity/release boundary, role, Affinity/progression, passive effects, skills and skill levels, talents/special research where represented, power and manuscript/emblem/material requirements.

### Additional discovered families

The research sweep must also disposition and, where inspectable, canonicalize:

- Alliance Technology;
- Truegold/Tempered Truegold systems;
- Hero XP and shard ladders;
- max-level/server-age unlocks;
- deployment/rally capacity;
- Infirmary/Storehouse/capacity progression;
- recruitment/unlock pools;
- VIP progression;
- Watchtower/Intel progression;
- Beast/Terror level caps;
- Bear Pitfall progression;
- recurring progression-scoring tables;
- materials/items and their factual progression uses;
- every other structured KingShot progression family discovered during implementation.

A newly discovered family is added to this contract/ledger before closeout. It cannot be silently deferred.

## Source registry

Every registered source contains at minimum:

- stable source key;
- publisher/maintainer and source label;
- canonical URI;
- source kind and authority tier;
- official/first-party indicator;
- locale;
- `retrieved_at` and `observed_at`/updated date when known;
- game-version/server-age/generation applicability when known;
- content checksum, upstream revision/blob checksum, or deterministic source-snapshot checksum when obtainable;
- licence/usage note and attribution requirement;
- source-specific locator where practical;
- access limitations, confidence notes and known conflicts.

### Authority tiers

- **Tier A — first party/official:** Century Games/KingShot official wiki/support/patch material and reviewed in-game evidence with immutable provenance.
- **Tier B — maintained structured community reference:** maintained KingShot databases/wikis or data APIs exposing inspectable tables, provenance and current updates.
- **Tier C — inspectable community/open-source dataset:** GitHub or equivalent repositories where underlying values and revision can be inspected.
- **Tier D — community observation/discussion:** Reddit, Discord, guides and similar discussion material. Tier D is primarily discovery/convention evidence and cannot alone silently establish an authoritative numeric table.

## Evidence/confidence status

Facts/conventions expose explicit statuses equivalent to:

- `official`;
- `corroborated`;
- `confirmed`;
- `single_source`;
- `community_convention`;
- `observed_in_game`;
- `conflicting`;
- `superseded`;
- `unknown`.

Numeric confidence scores from a source may be preserved in addition to the normalized status. The UX must not collapse all statuses to a generic “verified” badge.

## Versioning and immutable releases

A dataset release contains stable release ID, schema version, human dataset version, generated/reviewed timestamps, complete source manifest, deterministic checksum, per-family counts, conflict/unresolved counts, coverage report and release notes.

Published releases are immutable. A changed source snapshot, parser, normalization/reconciliation rule or semantic fact produces a new release. Historical releases remain loadable for observations/loadouts pinned to them.

Re-importing identical source snapshots and normalization rules must produce identical semantic content/checksum and no duplicate facts.

## Reconciliation rules

1. Normalize source-specific names to explicit candidates and preserve aliases/source spelling.
2. Reconcile by stable identity/source locator; never fuzzy-name overwrite.
3. Exact agreement across independent sources increases confidence while preserving every provenance link.
4. When a current, inspectable, higher-confidence source explicitly documents why an older conflicting table is stale, the current value may be canonical while the old claim remains a recorded conflict/superseded claim.
5. If disagreement remains unresolved for the same scope/version, publish an explicit conflict instead of last-write-wins.
6. Units must be explicit before numeric comparison.
7. Percentages/rates use one documented canonical representation without changing meaning.
8. Durations are base durations when sourced as base values; player-buffed times are not canonical costs.
9. Derived totals are allowed only from complete compatible component rows and are flagged as derived with inputs traceable.
10. Research/build prerequisite graphs must resolve and be cycle-checked.
11. A conflict cannot be hidden to satisfy a calculator prerequisite.
12. Open source metadata/confidence can be imported as provenance, but strategy/tier recommendation fields are discarded.

## Calculator evidence gate

This capability does not implement or unlock calculators.

A factual table may be accepted into the corpus when it is openly inspectable, attributed and sufficiently corroborated for reference. Calculator-target rows additionally require the repository's existing calculator evidence threshold: source/version/unit completeness plus the required official or independently corroborated evidence package. Until that separate threshold is satisfied, calculator status remains `Evidence-gated`.

## Canonical storage/import contract

Reviewed source snapshots/manifests and normalized releases are repository-owned artifacts. Runtime reads never depend on live third-party availability.

```text
source registry
    -> immutable source snapshot/manifest
    -> source-specific parser/normalizer
    -> normalized candidates
    -> schema + unit validation
    -> reconciliation
    -> conflict + completeness report
    -> immutable canonical release
```

Imports are idempotent and all-or-nothing. Publication occurs only after validation/reconciliation completes. Importers must retain source attribution/licence requirements and strip non-factual recommendation fields.

## Governor observation contract

A normalized Governor Hero observation may include, when actually observed: Hero catalogue ID, level, star/substar/tier, skill levels, observed Hero Gear slots/levels, Mastery levels, Widget level, observation time, source/provenance and confidence/review state.

Observations are append-only history. Absence does not prove non-ownership unless the evidence explicitly represents a complete roster capture. Missing fields remain unknown/not observed.

## Saved loadout contract

A saved loadout is user planning intent with a name, optional mode/purpose, canonical Hero references, optional named formation or explicit percentages, pinned dataset release, user notes, ownership/scope and attribution. The application validates identity/ratios only; it does not score or recommend the composition.

## Authorization

- Authenticated, verified users with an active Player may browse the factual catalogue.
- Alliance-specific observations/loadouts additionally require the existing concrete Alliance/Player permissions of their owners.
- End users cannot directly mutate canonical game truth.
- Import/reconciliation/publication is a controlled maintenance/platform boundary with audit provenance.
- Recording/correcting Governor observations uses existing `Intelligence/Roster` authority.
- Loadout mutation uses the existing Operations owner authorization.

## UX contract

### Progression Library

The factual surface provides search, family/generation/troop/version filters where meaningful, bounded presentation, explicit unknown/conflict states, evidence/confidence text, dataset version/checksum and source provenance.

### Entity/family detail

Hero, Gear, Charm, Building, Troop, Research, Pet and Master detail exposes factual progression rows and row/family provenance. A conflict shows competing claims; it never silently chooses a value without a documented reconciliation reason.

### Formations

Formation cards show ratio, scope, source and `Community convention`. There is no ranking/score/recommended sort.

### Governor Progression

Roster history displays normalized Hero observations alongside existing observed power/progression facts while preserving append-only semantics and explicit unknowns.

### Loadouts

Loadouts use accessible canonical Hero selection, expose the pinned dataset version and explicitly identify the record as user planning intent.

### Required states

Mobile/desktop UX covers loading, no dataset, no results, complete factual result, single-source/low-confidence result, unknown, conflict, superseded release, stale source, permission denied and authorized import/reconciliation diagnostics. Controls are keyboard usable, semantically labelled and localized; deterministic visual coverage is added where repository policy requires it.

## Completeness/conflict reporting

Every release emits a machine-readable per-family report with entities discovered/canonicalized, facts imported, corroborated/single-source/community-convention counts, conflicts, unresolved references, exclusions, source coverage and reason for any non-canonicalized row.

A family cannot be marked complete while a discovered structured entity is silently absent. With the open-data policy above, a complete reusable public table must be imported rather than left index-only unless a concrete conflict/licensing/schema reason is documented.

## Acceptance criteria

- **AC-1 Product/architecture truth:** `/docs/product`, architecture/reference docs and implementation agree on GameWorld catalogue ownership, Intelligence observation ownership and Operations planning ownership.
- **AC-2 Source provenance:** every canonical fact/convention traces to a registered source/release; source/version/unit/licence metadata is validated.
- **AC-3 Reproducible releases:** identical reviewed source input produces deterministic content/checksum and idempotent import; semantic change produces a new immutable release.
- **AC-4 Conflict safety:** unresolved contradictory values remain explicit and cannot silently become calculator truth.
- **AC-5 Hero completeness:** every discovered Hero plus skills/progression/shards/XP/exclusive-equipment family is canonicalized or explicitly dispositioned; complete reusable open tables are imported.
- **AC-6 Gear/Charm completeness:** Hero Gear, Mastery Forge, Governor Gear and Charms are canonicalized to the finest inspectable row level, including materials/effects/confidence/conflicts.
- **AC-7 Formation honesty:** named ratios are source-scoped conventions summing to 100 with no recommendation semantics.
- **AC-8 Building/troop completeness:** discovered per-level building/unlock/Truegold and troop-tier tables are imported or explicitly conflicted/excluded with resolvable relationships.
- **AC-9 Research completeness:** Academy and War Academy technologies/levels/costs/effects/times/power/prerequisites exposed by selected structured sources are represented and graph-validated.
- **AC-10 Pets/Masters/additional families:** Pets, Masters, Alliance Tech, VIP, Truegold, capacity/material and every other discovered factual family has canonical data or an explicit justified disposition.
- **AC-11 Observation separation:** normalized Hero observations extend append-only Roster history without making catalogue facts Player-owned or interpreting missing observations as zero/non-ownership.
- **AC-12 Loadout separation:** saved loadouts are independently persisted planning intent pinned to a dataset release and never stored as observations/catalogue facts.
- **AC-13 Factual UX:** authorized users can browse detailed rows/provenance/confidence/conflicts on mobile/desktop with accessibility/localization parity and no recommendation behavior.
- **AC-14 Calculator gate:** no calculator is introduced; only a later separately evidenced capability may unlock one.
- **AC-15 Completeness proof:** release reports have no silently missing discovered structured rows; index-only dispositions are forbidden for otherwise reusable complete tables.
- **AC-16 Import safety:** source import/normalization is idempotent, deterministic, recommendation-field stripping is tested and failed imports cannot partially publish.
- **AC-17 Repository Definition of Done:** behavior, authorization, idempotency, audit/observability, responsive UX, accessibility, localization, tests, visual coverage and applicable repository release gates pass on one immutable candidate.

## Delivery ledger

`Complete` means the exit condition is met; scaffolding/representative rows do not count.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Product contract | This contract defines outcome, taxonomy, open-data reuse, evidence/reconciliation, ownership, UX, authorization, acceptance criteria and calculator gate. |
| 1 | In progress | Architecture + release foundation | Ownership docs, source registry, immutable releases, deterministic checksum, idempotent import/publication and validation tests are complete. |
| 2 | In progress | Source discovery + source snapshots | Official/community wiki/database/open API/GitHub/community sources are surveyed; reusable complete tables are snapshotted/imported rather than index-only. |
| 3 | In progress | Heroes | Full discovered Hero identity/acquisition/progression/XP/shards/skills/aliases are canonicalized/dispositioned. |
| 4 | In progress | Exclusive equipment + Widgets | Full inspectable Widget levels/costs/effects/unlocks are canonicalized/dispositioned. |
| 5 | In progress | Hero Gear + Mastery | Full inspectable Gear enhancement/rarity/material/stat and Mastery Forge ladders are canonicalized/dispositioned. |
| 6 | In progress | Governor Gear + Charms | Complete open per-step Gear/Charm tables, slots/classes/materials/effects/power/confidence/conflicts are canonicalized. |
| 7 | In progress | Named formations | Repeatedly documented ratios remain source/mode scoped and explicitly non-recommendation. |
| 8 | In progress | Buildings + unlocks | Complete reusable building level/prerequisite/cost/time/power/capacity/unlock/Truegold tables are canonicalized. |
| 9 | In progress | Troops | Complete reusable troop tiers/stats/unlocks/training/promotion/T11/T12/Truegold facts are canonicalized. |
| 10 | In progress | Academy + War Academy | Discovered per-tech/per-level tables are canonicalized and dependency/unit/schema validated. |
| 11 | In progress | Pets + Masters | Discovered progression/skills/material/Affinity/talent/research facts are canonicalized/dispositioned. |
| 12 | In progress | Additional progression families | Alliance Tech, Truegold, VIP, capacity, materials, Watchtower/Beast/Terror/Bear Pitfall and every other discovered structured family is canonicalized or explicitly justified. |
| 13 | In progress | Governor Hero observations | Roster history retains normalized sourced Hero observations with dataset pins and unknown-safe semantics. |
| 14 | In progress | Saved loadouts | Authorized planning intent references canonical Heroes/formations/dataset release without recommendation semantics. |
| 15 | In progress | Progression Library UX | Detailed rows, search/filters, provenance/confidence/conflict/unknown states, formations, observations and loadouts are responsive/accessibility/localization complete. |
| 16 | In progress | Completeness/reconciliation gates | Machine-readable coverage/conflict/source/licence/unit/reference checks, source-snapshot checksums and idempotency/no-silent-omission tests are green. |
| 17 | In progress | Final reconciliation + release gates | Spec→code, code→spec, source→data, data→source, source→disposition, UX→backend, authorization and ownership scans are reconciled; all applicable repository gates pass on one immutable candidate. |

After each completed requirement implementation proceeds directly to the next incomplete ledger row. Research that exposes another progression family, source conflict, provenance requirement or architecture/UX gap updates this contract and is implemented before closeout.

## Closeout rule

The capability is complete only when the public progression source surface has been systematically surveyed; reusable complete factual tables have been canonicalized rather than left as index-only pointers; all remaining exclusions/conflicts are explicit; source/provenance/confidence is visible; Governor observations and saved loadouts remain separate; imports/checksums/reconciliation are deterministic and idempotent; mobile/accessibility/localization are complete; calculator evidence gates remain intact; and one immutable candidate passes every applicable repository release gate.