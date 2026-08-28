# Intelligence Change Detection

Status: Current complete capability

Date: 2026-08-28

## Product outcome

Intelligence Change Detection converts authorized historical facts and observations into deterministic, recomputable, source-cited signals that describe what changed, became stale, disappeared/reappeared under a complete-source contract, or is approaching a known validity boundary.

The capability does not predict intent, infer causation, assess threat, rank quality, or recommend strategy. It preserves the product's factual discipline: a difference between observations proves only that the observations differ, not exactly when the in-game change occurred or why it occurred.

A representative Assistant answer is:

> Observation: ABC Alliance was recorded at 4.2B power on August 22, up from 3.9B on August 15.

The capability must not transform that evidence into unsupported conclusions such as `ABC is preparing to attack you`.

## Delivery rule

This document and the related `/docs/product` ledger are the implementation source of truth. Delivery is complete only when every documented requirement, acceptance criterion, consumer integration, UX state, test obligation and delivery-ledger item is implemented, reconciled and verified.

Implementation must continuously reconcile documentation and code. If a later change reveals a missing rule, authorization boundary, provenance requirement, source-history edge case, UX state, integration dependency or better deterministic behavior, update this contract and implementation together; a material regression reopens the capability.

## Delivery result

Phase 11 is complete. Implementation candidate `e5c492f9391431ab68e1b2ca215038f448e5539d` passed repository CI, Intelligence Verification, Architecture V3 Verification, Visual Regression, CodeQL and Dependency Review, including production image/staging, backup/restore and scan gates. Final documentation reconciliation promotes Intelligence Change Detection to **Current complete capability** without changing the global state of unrelated selected or evidence-gated extensions.

The final visual defect found during verification was a scope-state error: an unscoped dashboard rendered the signal feed's empty state before a concrete active Alliance existed. The delivered UI now renders Command Overview signals only with a concrete active Alliance scope; an authorized scoped feed with zero signals still presents the legitimate empty state.

## Architectural ownership

Intelligence Change Detection is read-side composition, not a new source of business truth.

Canonical owners remain:

- `Intelligence/Observations` — durable Alliance/Kingdom observations and tracking history;
- `Intelligence/Roster` — roster intelligence and append-only Governor progression observations;
- `Intelligence/Evidence` — uploaded evidence, extraction/review provenance and commit receipts, never accepted destination truth;
- `Intelligence/Diplomacy` — diplomacy relationship and contact facts;
- `GameWorld/KingdomTransfers` — accepted Transfer observations, validity and derived eligibility;
- `Operations/Results` and existing Event analysis owner reads — accepted Bear Hunt results and comparable run history;
- `Alliance/Recruitment` — recruitment workflow state and explicit stage history;
- `Communications/Delivery` — notification preferences/delivery state only;
- `app/ReadModels/IntelligenceSignals` — authorized deterministic derivation/composition only.

Do not create a new `IntelligenceChange`, `ChangeDetection`, `Signals` or similar bounded context. Do not create a second intelligence database or an authoritative derived-signal table merely to share results between consumers.

Derived signals are recomputable values. A stable fingerprint provides identity/idempotency for consumers without converting a signal into persisted domain truth.

## Authorization boundary

Authorization occurs before retrieval.

Every signal request must resolve the active Governor and concrete Alliance/Kingdom/subject scope before owner records enter the candidate set. Broad cross-tenant retrieval followed by filtering is prohibited.

Owner write authorization remains unchanged because this capability performs no owner-domain mutation.

The Alliance Assistant consumes only typed signals that were already built from authorized owner reads. It must not independently query unrestricted raw histories and compare them in free-form logic.

## Provenance taxonomy

Signals preserve the existing source distinctions:

- `game_fact` — source-backed GameWorld truth;
- `operational_fact` — application state owned by Operations or another domain owner;
- `alliance_strategy` — Alliance-authored guidance/plan;
- `observation` — dated observed intelligence or Governor state;
- `evidence` — provenance supporting a reviewed observation/domain fact;
- `derived_signal` — deterministic recomputable interpretation over one or more owner facts.

A signal must expose enough source identity to explain the derivation, including source owner, subject identity, source record IDs, observation/result timestamps, baseline record IDs/timestamps when applicable, and Evidence/dataset identifiers when the source owner exposes them.

## Explicit state semantics

The capability preserves at least:

- `current`;
- `stale`;
- `missing`;
- `unknown`;
- `conflicting`;
- `unsupported`;
- `not_applicable`.

Missing is not zero. Missing is not stale. Missing is not disappearance. Missing is not expiry. Unknown/conflicting inputs never become confident signals merely to make a consumer look complete.

## Typed signal contract

Every signal returned to a consumer uses a typed immutable value with these concepts:

- signal `type`;
- signal `subjectType` and `subjectId`;
- optional `metric`/change kind;
- `detectedAsOf`;
- current observation/result timestamp;
- optional baseline timestamp;
- current value;
- optional previous value;
- optional absolute delta;
- optional percentage delta;
- freshness/validity state;
- materiality state;
- source owner/classification;
- source record IDs;
- optional Evidence IDs;
- optional Progression dataset ID/checksum;
- canonical source URL when available;
- stable deterministic `fingerprint`;
- rule version used for threshold/freshness interpretation.

The initial signal types are:

- `observation_change`;
- `stale_intelligence`;
- `tracked_entity_state_changed`;
- `transfer_evidence_expiring`;
- `progression_changed`;
- `performance_trend`;
- `recruitment_changed`.

Do not introduce strategic-intent signal types such as `attack_risk`, `getting_stronger`, `good_recruit`, `poor_performer`, `likely_transfer` or similar judgment labels.

## Deterministic fingerprinting

The fingerprint is a SHA-256 identity derived from the stable signal identity rather than display text. It includes, as applicable:

- signal type;
- subject type/id;
- metric/change kind;
- baseline source record ID;
- current source record ID;
- rule version.

The same source pair and rule version must produce the same fingerprint across requests. A corrected/invalidated source record naturally changes or removes the recomputed signal.

## Rule configuration

Freshness/materiality rules are backend configuration/service rules, never Vue/controller literals.

Rules are versioned. Initial configurable families include:

- Alliance power absolute/percentage materiality;
- Alliance member-count materiality;
- Alliance observation stale age;
- Governor progression stale age;
- Transfer `valid_until` expiring-soon horizon;
- Bear Hunt trend minimum comparable run count;
- Bear Hunt materiality where a consumer requests material-only trends.

A universal threshold across unrelated metrics is prohibited.

## Alliance observation signals

### Power change

Derive `observation_change` for Alliance power only from two accepted, non-invalidated observations for the same tracked Alliance. Use decimal-safe power arithmetic.

Below-threshold changes may be omitted from material-only feeds while remaining available to an explicit history comparison surface when that surface already supports raw comparison.

### Member-count change

Derive `observation_change` for member count only when both comparable observations contain a member count.

### Staleness

Derive `stale_intelligence` when the latest accepted observation exceeds the configured freshness boundary. A missing observation remains `missing`, not stale.

### Disappearance/reappearance

`tracked_entity_state_changed` is permitted only when the source contract proves absence is meaningful.

Absence in a partial scrape, incomplete import, failed ingestion run or otherwise non-exhaustive capture must not generate disappearance.

A valid disappearance requires an explicit complete-enough source/capture semantic proving the tracked entity was observed absent. Reappearance requires a later comparable complete-enough observation proving presence again.

No current ordinary Alliance observation history proves these complete-source semantics, so disappearance/reappearance remains `unsupported` at runtime for that source. The implementation exposes the typed derivation primitive only for a future source that explicitly proves complete-source presence/absence; it does not invent a completeness flag from ordinary tracking state.

## Governor progression signals

Governor progression change detection uses append-only `Intelligence/Roster` progression observations.

Comparable facts may include, where present:

- Governor name/power/progression level/observed Alliance tag/Kingdom number;
- Hero membership;
- Hero level/star/substar/widget level;
- Hero Gear quality/level/mastery;
- Governor Gear quality/level/star;
- Charm identity/level.

Signals preserve source observation IDs, Evidence/review IDs and Progression dataset identity/checksum.

A Hero may be considered observed absent only when the later source observation explicitly represents a complete roster capture. Missing a Hero from a partial Hero observation is not absence.

`stale_intelligence` for Governor progression uses the configured presentation freshness boundary. Staleness never invalidates the original immutable observation.

## Transfer validity signals

Transfer signal derivation reads canonical `GameWorld/KingdomTransfers` observations.

`transfer_evidence_expiring` is the user-facing signal type but expiry semantics come from the accepted Transfer observation's `observed_at`/`valid_until`, not from the Evidence binary's retention state.

Signals may state that a specific observation expires soon or is expired. They must not independently decide that a Governor can/cannot transfer; eligibility remains the KingdomTransfers owner's deterministic result.

## Bear Hunt performance trends

Bear Hunt trend derivation reuses authoritative completed-run history from existing Operations/EventAnalysis owner reads. It does not create a second Bear Hunt statistics store.

A `performance_trend` requires the configured minimum number of comparable completed runs and a metric available in every compared run.

Supported initial metrics may include:

- Alliance total damage;
- active Governor personal damage;
- recorded attendance rate when available;
- recorded Rally participation when available.

Zero remains distinct from unavailable/missing. An unavailable run breaks the comparable trend rather than being converted to zero.

Trend wording remains descriptive, for example `Alliance total damage increased across the last three comparable completed runs`. It must not become `strategy improved` or `performance is bad`.

## Recruitment change signals

Recruitment uses `Alliance/Recruitment` owner history.

Initial `recruitment_changed` subtypes are limited to owner-proven workflow transitions such as:

- `stage_changed`;
- `candidate_accepted`;
- `candidate_declined`;
- `candidate_withdrawn`;
- `candidate_joined`;
- `follow_up_due` when represented by the current owner workflow.

A generic `updated_at` timestamp must never be used to fabricate before/after candidate facts. Fields without authoritative history do not produce historical change signals.

## Unified authorized signal query

`app/ReadModels/IntelligenceSignals` exposes bounded query entry points for concrete scopes such as Alliance, Kingdom, Governor or subject history.

The composition query:

1. receives already-resolved actor/scope references;
2. invokes owner-bounded queries;
3. derives typed signals;
4. excludes unsupported/invalid derivations;
5. deduplicates by fingerprint;
6. orders deterministically by factual recency then stable identity;
7. enforces result/query limits;
8. returns values only and performs no mutation.

## Command Overview integration

Command Overview exposes a bounded `Recent intelligence changes` section with a small result limit only after a concrete active Alliance scope exists and Intelligence view authority is proven. An unscoped dashboard does not render a synthetic empty feed.

Within an authorized scope, the section distinguishes informational changes from actionable attention. A factual change must not automatically inflate the global action count. Only a state explicitly defined by the product contract as requiring attention may contribute to an attention count.

Each item exposes a neutral description, observed/baseline timestamps, source classification and canonical destination link.

## Kingdom Intelligence integration

Kingdom Intelligence exposes the typed change feed alongside existing latest/prior/7-day/30-day comparisons.

The user can identify recent material changes and stale intelligence without losing access to the underlying observation history.

Supported filters may include signal type, subject, recency, freshness/materiality and source classification. Filtering must not weaken tenant/permission boundaries.

## Alliance Assistant integration

The Assistant adds a bounded `intelligence_changes` intent for questions such as:

- What changed with ABC Alliance?
- Has anything changed in Kingdom 123?
- What intelligence is stale?
- Has my progression changed since the last observation?
- How has our Bear Hunt performance changed recently?

The Assistant uses typed authorized signals and server-built citations. It performs no mutation and does not infer strategy or intent from the signal.

## Communications integration

Communications may deliver subscribed change notifications. Communications owns only recipient preferences, channel/provider delivery, acknowledgement, retry/failure, inbox/read/dismiss state and delivery idempotency.

The underlying signal remains recomputable read-side state. Notification idempotency keys use the stable signal fingerprint plus recipient/channel/policy identity as appropriate.

No persisted signal table is introduced merely to support notification delivery.

## UX states

Material consumer surfaces support:

- loading;
- populated;
- empty/no material changes for an authorized concrete scope;
- stale;
- missing/unknown;
- unsupported;
- error/retry;
- filtered-empty;
- permission-safe unavailable/unscoped state.

Signals use neutral language (`increased`, `decreased`, `changed`, `became stale`, `expires soon`, `reappeared`) rather than positive/negative strategic coloring. Meaning must not depend on color alone.

Every material surface is responsive, keyboard navigable, screen-reader usable and localized.

## Observability and privacy

Diagnostics may record signal type, owner/query family, count, latency, rule version and privacy-safe scope identifiers according to repository policy.

Do not log Evidence payloads, private extracted text, arbitrary free-form recruitment content or unrestricted raw owner records merely to debug derivation.

## Acceptance criteria

- **ICD-01** No new bounded context or authoritative derived-signal persistence store is introduced.
- **ICD-02** All signal derivation is deterministic and recomputable from authorized owner state.
- **ICD-03** Authorization occurs before owner retrieval; cross-Alliance/Kingdom leakage is covered by tests.
- **ICD-04** Every signal has a typed signal type, subject identity, source classification, factual timestamp(s), source record identity and stable fingerprint.
- **ICD-05** Identical source pairs and rule version produce identical fingerprints.
- **ICD-06** Invalidated/replaced source observations are not used; recomputation reflects corrections without rewriting a signal table.
- **ICD-07** Alliance power uses decimal-safe comparison and documented materiality.
- **ICD-08** Alliance member-count change requires values on both observations.
- **ICD-09** `missing` and `stale` remain distinct at exact threshold boundaries.
- **ICD-10** Partial/incomplete source absence never generates disappearance.
- **ICD-11** Disappearance/reappearance remains unsupported until owner/source history proves complete-enough presence/absence semantics.
- **ICD-12** Governor progression changes preserve Evidence/review and Progression dataset identity/checksum.
- **ICD-13** Hero absence requires an explicit complete-roster capture.
- **ICD-14** Transfer expiry derives from canonical Transfer observation `valid_until`; signal derivation never independently changes eligibility.
- **ICD-15** Bear Hunt trends require the configured minimum comparable completed runs; missing/unrecorded is never converted to zero.
- **ICD-16** Recruitment changes use explicit owner history; `updated_at` alone never fabricates a prior value.
- **ICD-17** Signal sorting/deduplication is deterministic and bounded by query/result budgets.
- **ICD-18** Command Overview shows a bounded recent-change feed without treating every factual change as an action, and does not render the feed before a concrete active Alliance scope exists.
- **ICD-19** Kingdom Intelligence exposes typed change signals with underlying source links/provenance.
- **ICD-20** Alliance Assistant `intelligence_changes` answers consume typed authorized signals, use server-built citations and perform zero mutation.
- **ICD-21** Assistant output states observation differences without unsupported causation, threat or strategy conclusions.
- **ICD-22** Communications notification delivery, when enabled for a signal policy, uses the signal fingerprint for idempotent delivery without becoming signal owner.
- **ICD-23** Backend configuration/services own thresholds/freshness/trend rules; Vue/controllers contain no hidden business thresholds.
- **ICD-24** Empty/loading/stale/missing/unsupported/error/filtered-empty/unscoped states are explicit and localized.
- **ICD-25** Command Overview and Kingdom Intelligence signal UX is responsive, keyboard/screen-reader usable and covered by applicable visual regression.
- **ICD-26** Privacy-safe diagnostics and query budgets are tested.
- **ICD-27** Architecture tests prevent owner contexts from importing `ReadModels/IntelligenceSignals` and prevent a new signal persistence owner.
- **ICD-28** PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression and repository security/release gates pass as applicable.
- **ICD-29** Documentation is reconciled with delivered behavior before status promotion.
- **ICD-30** The capability is promoted to Current complete only when every non-evidence-gated ledger item below is complete and verified.

## Delivery ledger

| Phase | Delivery item | State |
| --- | --- | --- |
| ICD-0 | Product contract, ownership/provenance, taxonomy, UX states and release criteria | Complete |
| ICD-1 | Typed signal enums/value object, deterministic fingerprint and rule configuration | Complete |
| ICD-2 | Alliance power/member/freshness derivation | Complete |
| ICD-3 | Complete-source disappearance/reappearance semantics or explicit unsupported disposition | Complete |
| ICD-4 | Governor progression change/staleness derivation with dataset/Evidence provenance | Complete |
| ICD-5 | Transfer validity/expiry derivation | Complete |
| ICD-6 | Bear Hunt comparable-run trend derivation | Complete |
| ICD-7 | Recruitment owner-history change derivation | Complete |
| ICD-8 | Unified authorized signal composition, dedupe/sort/query budgets | Complete |
| ICD-9 | Kingdom Intelligence integration and UX | Complete |
| ICD-10 | Command Overview integration and UX | Complete |
| ICD-11 | Alliance Assistant bounded change intent/citations | Complete |
| ICD-12 | Communications idempotent delivery integration/preferences where supported | Complete |
| ICD-13 | Localization, accessibility, responsive behavior, visual regression, privacy diagnostics | Complete |
| ICD-14 | Architecture/behavior/query-budget/security/release verification | Complete |
| ICD-15 | Final `/docs/product` reconciliation and capability-status promotion | Complete |

## Completion rule

A failing test, incomplete UX state, missing source projection, authorization gap, provenance problem, query-budget failure, architecture violation, documentation mismatch or integration defect reopens the applicable delivery item; it is not ignored because the capability was previously closed.

Intelligence Change Detection is complete because every applicable acceptance criterion and delivery-ledger item has been implemented and verified. Unsupported behavior remains explicitly unsupported rather than guessed: ordinary Alliance observation history still does not emit disappearance/reappearance until an owner/source contract proves complete-enough presence/absence semantics.
