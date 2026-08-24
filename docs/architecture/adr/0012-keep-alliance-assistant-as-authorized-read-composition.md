# ADR 0012 — Keep Alliance Assistant as authorized read composition

Status: Accepted — 2026-08-24

## Context

Alliance Assistant needs to answer cross-capability questions such as `What time is Swordland and am I rostered?` by combining Events and Rosters, while also surfacing Alliance-authored guides and Intelligence observations with provenance.

Creating an Assistant bounded context, privileged AI database, universal search index, or elevated service account would duplicate ownership and weaken the existing authorization boundaries. Treating model training knowledge as application truth would also make answers unauditable and mix Alliance strategy/observations with factual game data.

## Decision

1. Alliance Assistant is implemented as `app/ReadModels/AllianceAssistant`, not a bounded context.
2. It owns no business persistence and has no write authority.
3. Each source remains owned and authorized by its existing capability. Authorization occurs before data is returned to Assistant composition.
4. Assistant evidence is transient and explicitly classified as operational fact, game fact, Alliance strategy, or observation.
5. Citations are server-generated from the exact authorized evidence used by the answer.
6. Alliance strategy and observations are never promoted to `game_fact`.
7. The first release uses deterministic bounded intent parsing and makes no external model/provider call.
8. Localized suggestion controls use closed prompt identifiers that map to deterministic read intents; prompt identifiers do not grant authority.
9. Unsupported/open-ended questions do not fall back to model knowledge.
10. Write-like questions do not mutate state. Any future write handoff must call the existing owner Action and retain that owner's authorization, validation, idempotency, audit/outbox, observability, and recovery contract.
11. Conversation/question/answer/evidence state is not persisted by the current capability.

## Consequences

- Cross-context query composition is explicit and testable.
- No Assistant-specific source of truth can drift from Events, Rosters, Content, Observations, or GameWorld.
- Tenant isolation is enforced before retrieval instead of trying to redact model output afterward.
- Source provenance remains visible to users and machine-testable.
- A future LLM/provider integration is a new architectural decision, not a configuration flip. It requires product/architecture review of data minimization, provider handling, prompt injection, timeouts/retries, and evidence validation.
- A future action handoff is likewise an explicit extension and cannot directly import write repositories/models into the ReadModel.

## Rejected alternatives

### New `Assistant` bounded context

Rejected because the capability owns no independent business truth or consistency boundary.

### Retrieve globally and filter after generation

Rejected because unauthorized data could enter prompts, caches, diagnostics, citations, or generated prose before filtering.

### Elevated Assistant service identity

Rejected because it creates a second authorization model and makes least-privilege/tenant-isolation guarantees harder to prove.

### Unconstrained KingShot chatbot

Rejected because provider/model knowledge is not source-labelled application truth and cannot satisfy the citation/provenance contract.

### Direct Assistant writes

Rejected because they would bypass existing owner authorization, validation, idempotency, audit/outbox, and recovery behavior.
