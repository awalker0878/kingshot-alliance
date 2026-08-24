# ADR 0012 — Keep Alliance Assistant as authorized read composition

Status: Accepted — amended 2026-08-24 for bounded GameWorld extension

## Context

Alliance Assistant answers cross-capability questions by combining data already owned by application capabilities. The initial release combined Events/Rosters, Alliance Content and Intelligence observations. The extension adds bounded Progression facts, the active Governor's own participation and battle-plan assignment, their authorized transfer assessment, and immutable territory revisions attached to Events.

Creating an Assistant bounded context, privileged AI database, universal search index, elevated service account, or Assistant-owned factual layer would duplicate ownership and weaken existing authorization boundaries. Treating model training knowledge as application truth would also make answers unauditable and mix Alliance strategy/observations with factual game data.

Existing management projections are not automatically appropriate Assistant dependencies. Some legitimately retrieve many Governors, assignments, participants or revisions. Passing those broad results into the Assistant and filtering afterward would violate authorization-before-retrieval even if the final response were redacted correctly.

## Decision

1. Alliance Assistant remains `app/ReadModels/AllianceAssistant`, not a bounded context.
2. It owns no business persistence and has no write authority.
3. Each source remains owned and authorized by its existing capability. Authorization occurs before private data is returned to Assistant composition.
4. When an existing owner projection is broader than the Assistant question, the owner exposes a narrow self/minimum query first. The Assistant does not consume a broad management projection and filter it.
5. `GameWorld/Progression` owns bounded `game_fact` resolution, release selection, source IDs, confidence/evidence status, and `known | unknown | conflicting` semantics. The Assistant may not resolve or fill those states itself.
6. Assistant evidence is transient and explicitly classified as operational fact, game fact, Alliance strategy, or observation.
7. Citations are server-generated from the exact authorized evidence used by the answer.
8. Alliance strategy and observations are never promoted to `game_fact`.
9. The release continues to use deterministic bounded intent parsing and makes no external model/provider call.
10. Localized suggestion controls use closed prompt identifiers that map to deterministic intents; prompt identifiers do not grant authority.
11. Unsupported/open-ended questions do not fall back to model knowledge.
12. Write-like questions never mutate state through the Assistant. A recognized workflow may return a server-created **navigation-only** handoff to the normal application surface. That destination performs its normal authorization, validation, stale-context and mutation flow.
13. Alliance Assistant does not import owner Actions, submit hidden forms, automatically POST after handoff, or create an Assistant-specific privileged Action.
14. Conversation/question/answer/evidence/handoff state is not persisted by the current capability.

## Consequences

- Cross-context query composition remains explicit and testable.
- No Assistant-specific source of truth can drift from Events, Participation, Rosters, BattlePlans, Content, Observations, Progression, KingdomTransfers or TerritoryPlanning.
- Tenant isolation is enforced before retrieval instead of trying to redact output afterward.
- Game facts preserve immutable dataset release/checksum/source provenance.
- Unknown and conflicting factual rows stay unknown/conflicting to the user.
- Private self questions avoid loading other Governors' participation, assignment or transfer evidence.
- Territory answers point at immutable published Event-attached revisions rather than mutable planning heads.
- Safe navigation handoff can improve usability without weakening the existing write model.
- A future LLM/provider integration remains a new architectural decision, not a configuration flip. It requires product/architecture review of data minimization, provider handling, prompt injection, timeouts/retries, and evidence validation.

## Rejected alternatives

### New `Assistant` bounded context

Rejected because the capability owns no independent business truth or consistency boundary.

### Retrieve globally and filter after generation/composition

Rejected because unauthorized or irrelevant data could enter prompts, caches, diagnostics, citations or generated prose before filtering. This also applies to broad internal management projections.

### Assistant-owned Progression index

Rejected because `GameWorld/Progression` already owns immutable source-backed releases and conflict/source-gap semantics. A second index would duplicate truth and drift.

### Assistant conflict resolution

Rejected because choosing among conflicting Progression evidence is a data-governance decision owned by GameWorld, not answer composition.

### Elevated Assistant service identity

Rejected because it creates a second authorization model and makes least-privilege/tenant-isolation guarantees harder to prove.

### Unconstrained KingShot chatbot

Rejected because provider/model knowledge is not source-labelled application truth and cannot satisfy the citation/provenance contract.

### Direct Assistant writes

Rejected because they would bypass existing owner authorization, validation, idempotency, audit/outbox and recovery behavior.

### Assistant Action proxy

Rejected for the current capability. A typed navigation link is sufficient for recognized writes and preserves the normal application mutation boundary.
