# Kingshot Event Type Framework

Status: Phase 13 implementation source of truth — 2026-08-28

## Purpose

Phase 13 replaces the repository's generic event-type profile assumptions with a verified Kingshot Event Type Framework. The framework answers which application workflows apply to a verified Kingshot event. It does not encode or infer Kingshot game mechanics.

The required enablement chain is:

`Kingshot event identity` → `source/evidence established` → `canonical event name established` → `supported workflow dimensions identified` → `game-rule assumptions separated from application workflow` → `event profile enabled`.

No specialized event behavior may bypass this chain.

## Implementation findings that define the migration

The pre-Phase-13 `Operations/Events` catalog mixes workflow applicability with hard-coded game-mechanic assumptions. Examples include minimum Town Center levels, alliance-member minimums, matchmaking anchors, combatant/substitute capacities, battle windows, occupation durations, rank restrictions, cooldowns, progression/scoring assumptions and event-specific phase timing. Phase 13 removes those assumptions from event profiles. Existing code or tests are not evidence that those game mechanics are true.

The pre-Phase-13 `Operations/Results` metric catalogue also assigns event-specific score/metric schemas to event identities whose profiles are not verified. That is an indirect specialized-behavior path and is prohibited by the same gate. A candidate/profile-disabled event cannot receive a seeded result schema, scoring assumption, inferred metric, aggregation rule or `higher_is_better` assertion merely because its slug appears in a catalogue.

The pre-Phase-13 `KingPerkEventCapabilityCatalog` also maps the separate current-complete King Perks capability to the `kingdom-of-power` event type through a generic `king_perks` event-capability flag. The repository establishes King Perks as an Operations capability, but that hard-coded association is not sufficient evidence that Kingdom of Power canonically enables King Perks. Phase 13 therefore removes the association from Event profile truth. King Perks remains independently owned and functional; any future event-specific King Perks composition requires an explicit product/evidence contract rather than reuse of the deleted generic capability flag.

Where the repository cannot establish a mechanic, result field or cross-capability event relationship through the canonical owning evidence/product contract, the value is removed and remains unknown/unsupported. Phase 13 does not relocate unsupported mechanics into another generic configuration, metric object or workflow dimension.

## Ownership

`Operations/Events` continues to own application Event and occurrence identity/schedule plus the stable reference to a canonical event type. It owns the typed workflow-profile contract because that contract determines which existing Operations/read-model workflows may compose for an occurrence.

Other owners retain their facts and writes:

- `Operations/Participation` owns registration, responses, waitlist and attendance;
- `Operations/Rosters` owns roster state;
- `Operations/BattlePlans` owns objectives/assignments;
- `Operations/Rallies` owns Rally planning and recorded participation;
- `Operations/Results` owns Event results and accepted Bear Hunt reports;
- `Operations/TerritoryPlanning` owns desired territory-planning intent/revisions;
- `Operations/KingPerks` owns King Perk/King Skill planning independently of Event profile truth unless a separately evidenced event relationship is established;
- `Intelligence/Evidence` owns evidence artifacts, extraction/review and commit provenance;
- `ReadModels/EventManagement` composes authorized readiness/closeout state without domain writes;
- GameWorld owners remain the only place for source-backed Kingshot rules/mechanics when such facts are actually qualified.

No `EventProfile` bounded context or duplicate workflow store is introduced.

## Identity, verification and profile state

Every event type has a stable server-owned canonical key independent of display/localization strings. Candidate or historical labels may exist without authorizing specialized behavior.

Verification state is explicit and typed:

- `candidate`: identity has been encountered but evidence is insufficient;
- `verified`: source/provenance establishes the Kingshot identity and canonical name;
- `conflicting`: available evidence conflicts materially and cannot establish one identity;
- `unsupported`: the event is known but specialized application support is intentionally not asserted.

Profile state is separately explicit and typed:

- `disabled`: workflow applicability is not enabled;
- `enabled`: the reviewed workflow dimensions may be consumed by application composition.

`disabled` is not equivalent to an enabled profile with zero workflow dimensions.

A profile may be enabled only when verification state is `verified`, evidence/provenance is present, canonical identity is established and every enabled workflow dimension has been deliberately reviewed.

## Provenance

A verified event identity preserves, at minimum, a source label, source reference/URI or stable evidence identifier, observation/review date, and an optional game/version boundary when known. Verification metadata is server-owned and auditable. A user-entered name, fixture, migration, localization key, community project or previous hard-coded catalog entry is not sufficient evidence by itself.

Bear Hunt is the repository's verified baseline because the application already has accepted, completed Bear Hunt workflows and evidence-oriented product contracts. Phase 13 establishes an explicit internal source record for that repository-supported identity; this is application provenance for the supported identity/workflows, not a claim about unqualified game mechanics.

All other pre-existing catalog identities remain candidate/profile-disabled unless the repository already contains reviewable source evidence sufficient to promote them. Their presence in the old catalog does not count as verification.

## Closed workflow-dimension vocabulary

The profile is a closed server-owned vocabulary of application workflow dimensions. Phase 13 supports these dimensions where established:

- `participation` — Participation responses/registration/attendance workflow applies;
- `roster` — Rosters workflow applies;
- `battle_assignments` — BattlePlans assignments/objectives workflow applies;
- `rallies` — Rallies workflow applies;
- `territory_plan` — a published TerritoryPlanning revision may participate;
- `results` — Results workflow applies;
- `screenshot_evidence` — supported Evidence intake may attach/commit through its existing owner boundary;
- `debrief` — supported event-analysis/debrief composition applies;
- `readiness_closeout` — Event Management may evaluate occurrence readiness/closeout using only applicable owner dimensions.

This vocabulary describes application composition only. It cannot be extended by arbitrary persisted strings or frontend configuration. Legacy generic capabilities outside this vocabulary, including `king_perks`, are not auto-mapped to a workflow dimension. A capability may remain independently usable without being an Event profile dimension.

A specialized action or read model that composes multiple dimensions must require every dimension it actually consumes. One enabled dimension never stands in for another. In particular, the Bear Hunt reviewed-screenshot commit path consumes both `screenshot_evidence` and `results`; it is available only when the resolved profile enables both dimensions. This composition rule does not grant either owning context authority over the other.

## Results schema boundary

`results` means the Results workflow may participate; it does not itself define a scoring system or metric catalogue. Result fields remain `Operations/Results`-owned and must be backed by the supported destination/evidence contract for that event.

For Bear Hunt, the current Screenshot Intake contract explicitly fixture-proves reported rank, Player display name and damage value, and explicitly states that `rallies_joined`, `rallies_led` and other unproven metrics must not be inferred. Phase 13 therefore permits the existing Bear Hunt damage/result destination semantics while removing generic event-metric assumptions not independently established by that contract.

Candidate/profile-disabled events receive no event-specific metric definitions from the event-type catalogue. Enabling an event's `results` workflow in the future does not automatically qualify any score, metric, aggregation, unit or ordering assertion; those remain separately evidence-backed Results semantics.

## Forbidden profile content

The Event Type Framework must not contain or infer scoring/damage formulas, rally limits, team sizes, eligibility rules, registration deadlines, battle timing, round counts, territory geometry, troop restrictions, reward thresholds, matchmaking behavior, progression requirements, rank restrictions, event cooldowns, occupation times, recommendation/strategy semantics, unpublished constants or other Kingshot mechanics.

Schedule fields that describe an Alliance-created application occurrence remain Operations facts. A Game-supplied schedule rule is a game fact and must not be presented as canonical unless sourced by its appropriate owner.

## Bear Hunt baseline profile

The canonical Bear Hunt event type is verified and profile-enabled. The repository demonstrates these workflow dimensions and Phase 13 may connect them without inventing game mechanics:

- `participation`;
- `roster`;
- `rallies`;
- `results`;
- `screenshot_evidence`;
- `debrief`;
- `readiness_closeout` only to the extent Event Management derives readiness from the above applicable owners and existing occurrence lifecycle.

Battle assignments and territory planning are not enabled merely because those capabilities exist elsewhere. They require separate evidence of applicability.

The profile contains no Bear Hunt cooldown, recurrence, rank, capacity, formation, damage formula or scoring mechanics.

## Candidate/new event behavior

A new event may be recorded as a candidate identity, but its profile remains disabled. It cannot inherit Bear Hunt behavior, infer capabilities by naming, category or similarity, or use a default all-capabilities profile. Name heuristics such as `hunt => rallies` or `battle => roster + assignments + results` are prohibited.

The application must preserve candidate, conflicting, unsupported and disabled states instead of coercing them to an empty enabled profile.

## Migration from the generic catalog

Phase 13 must:

1. add explicit verification/profile/provenance fields to canonical event types;
2. replace the broad `EventCapability` mechanics-oriented vocabulary with the closed workflow-dimension vocabulary;
3. reduce Bear Hunt to the repository-supported workflow profile;
4. retain other historical canonical keys only as candidate/profile-disabled identities unless evidence qualifies them;
5. remove hard-coded game mechanics and per-event default mechanic configuration from the catalog/profile path;
6. remove event-specific metric seeding for candidate/profile-disabled identities and preserve only separately supported Results schemas;
7. remove legacy cross-capability registrations, including the unverified `kingdom-of-power` → `king_perks` mapping, unless separately evidenced;
8. prevent inactive/disabled profiles from surfacing specialized workflow controls;
9. preserve existing Event/occurrence foreign keys and historical owner data;
10. migrate only deterministic canonical identities; ambiguous free-text labels must not be guessed;
11. migrate first-party consumers from display-name/category/capability heuristics to the typed resolved profile contract;
12. remove superseded profile mechanics/configuration paths rather than maintain dual reads or compatibility shims.

Because the application is not deployed, the final clean-database schema is authoritative. Existing migrations may be reconciled when necessary rather than preserving obsolete schema solely for compatibility.

## Authorization

Ordinary Event create/manage authority permits scheduling and operating supported occurrences; it does not grant authority to establish Kingshot game identity or profile truth. Event-type verification/profile enablement is a privileged server-owned/catalogue boundary. Runtime user input may never promote a candidate identity or activate a workflow dimension.

Operational Event scope/configuration actions may change only application-owned occurrence/scope settings explicitly retained by the product contract. They must not mutate verification state, profile state, workflow dimensions, source provenance or game/result truth.

All protected reads/writes retain authorization-before-retrieval and concrete scope requirements from the owning capability.

## Runtime contract

First-party consumers resolve a typed profile conceptually containing:

- stable event-type ID and canonical key;
- canonical display key/name;
- verification state;
- profile state;
- provenance summary appropriate to the caller;
- closed set of workflow dimensions.

Consumers must not branch specialized behavior on mutable display names, categories, arbitrary strings or the mere existence of an EventType row.

## UX contract

Event creation/editing surfaces may schedule verified+enabled event types for specialized workflows. Candidate/disabled identities are visibly unavailable for specialized behavior and must expose an appropriate disabled/unsupported explanation where relevant. Ordinary Alliance managers are not asked to establish game truth.

Read models and APIs must distinguish `profile_disabled` from `enabled_with_dimension_absent` and preserve unknown/conflicting/unsupported semantics.

## Acceptance criteria

Phase 13 is complete only when arbitrary event names cannot activate specialized behavior; an unverified event cannot have an enabled profile; Bear Hunt resolves to one stable verified identity; Bear Hunt exposes only repository-supported workflow dimensions; candidate events remain disabled; profile-disabled is distinguishable from dimension-absent; no workflow, result schema or unrelated capability relationship is inherited by name/category/similarity; one dimension never activates another; multi-dimension consumers require every dimension they consume; runtime profile resolution is deterministic; ordinary Event authority cannot establish profile truth; owner boundaries are unchanged; all hard-coded generic profile mechanics, unverified event-specific metric seeds and unverified legacy cross-capability registrations are removed from the profile path; first-party consumers use the canonical contract; existing Bear Hunt workflows regress cleanly; architecture/auth/isolation tests cover these rules; and `/docs/product` matches the implementation.

## Phase 13 delivery ledger

| Row | Delivery item | State |
| --- | --- | --- |
| P13-01 | Product contract and terminology reconciliation | Complete |
| P13-02 | Legacy generic-profile/catalog inventory | Complete |
| P13-03 | Canonical event identity + verification/profile state | In progress |
| P13-04 | Provenance contract and Bear Hunt verified source | In progress |
| P13-05 | Closed workflow-dimension vocabulary | In progress |
| P13-06 | Remove unsupported mechanic/result/cross-capability assumptions | In progress |
| P13-07 | Bear Hunt canonical enabled workflow profile | In progress |
| P13-08 | Candidate/new-event disabled behavior | In progress |
| P13-09 | Existing occurrence/data-preserving migration | In progress |
| P13-10 | Authorization/profile enablement boundary | In progress |
| P13-11 | Event Management/read-model integration | In progress |
| P13-12 | API/TypeScript/UI integration | In progress |
| P13-13 | Legacy profile mechanics/config removal | In progress |
| P13-14 | Domain/architecture/auth/migration tests | In progress |
| P13-15 | Bear Hunt regression coverage | In progress |
| P13-16 | Accessibility/localization/responsive states | In progress |
| P13-17 | Product docs/catalogue/journeys/global ledger reconciliation | In progress |
| P13-18 | Clean-database/static/build/security verification | In progress |
| P13-19 | Final implementation-versus-documentation reconciliation | In progress |

A row is not marked Complete based on intent. It closes only when implementation and verification evidence exist.

## Continuous reconciliation rule

If implementation reveals a missing requirement, authorization rule, event-identity ambiguity, provenance requirement, workflow-dimension boundary, migration problem, unsupported-mechanic risk, architectural boundary, UX state, integration dependency or better product behavior, update this product contract first and then implement that change.

Uncertainty is never resolved by inventing Kingshot mechanics. Insufficient evidence produces candidate, conflicting, unsupported, unknown or profile-disabled behavior as applicable.
