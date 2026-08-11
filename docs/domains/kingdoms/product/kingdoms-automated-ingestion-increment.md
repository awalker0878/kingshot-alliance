# Kingdoms automated game-data ingestion product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** In progress — `K4-P0` Complete; `K4-P1` / Slice A runtime validated; later slices gated  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; not historical Phase numbering or the completed DCP  
**Baseline dependency:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`  
**Implementation sequence:** [KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)  
**Living capability:** [Automated game-data ingestion](../automated-ingestion.md)

## 1. Purpose

`KINGDOMS-004` reduces repetitive manual entry of factual Kingshot player/game-Alliance observations through explicitly approved machine-readable sources while preserving accepted Kingdoms identity, tenancy, history, privacy, and human-decision boundaries.

K4 is an ingestion boundary, not an acquisition loophole. It does not authorize scraping, OCR, browser/game-client automation, bots, undocumented/unapproved APIs, credential capture, arbitrary tenant URLs, or any source simply because it is technically reachable.

## 2. Current product state

`K4-P1` now implements the generic control plane:

- code/config adapter registry;
- Alliance/current-Kingdom subscriptions;
- batches and bounded normalized candidates;
- deterministic source-window/candidate identity;
- quarantine/rejection;
- manager status/control; and
- internal audit/outbox evidence.

The **production adapter allowlist is empty**, so the current repository does not acquire real Kingshot data. There is no K4 scheduler/worker and no automatic promotion into K1/K3 observation history yet.

Exact Slice A runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` is protected-green; see [Slice A validation](kingdoms-automated-ingestion-slice-a-validation.md).

## 3. Core business rules

### Approved sources only

A runtime adapter must be explicitly registered/approved. Manager input cannot define arbitrary hosts/URLs/headers/credentials. A concrete source requires documented transport/permission, stable-ID/field contract, authentication/secret ownership, rate/timeout/cursor behavior, schema/version change handling, and revocation procedure before production enablement.

### Kingdoms owns ingestion semantics

Kingdoms owns source subscription state, batches/candidates, normalization, quarantine, later promotion, and resulting game-observation provenance. Integrations continues to own current public read API/outbound webhooks; K4 creates no public Kingdoms machine contract or generic credential vault.

### Tenant and Kingdom context are explicit

Each subscription belongs to one platform Alliance and captures one Kingdom context. Workers/human actions must re-resolve that context. Alliance-Kingdom drift preserves historical operational records but blocks new automated work instead of silently retargeting it.

### Stable game identifiers are the only automatic identity keys

Names, tags, handles, source row positions, and unproven source-local IDs never auto-match a player/game Alliance. Missing/ambiguous identity quarantines.

### Promotion is factual and existing-target-only

Later K4 promotion may create only:

- `PlayerSnapshot` for an existing roster entry/neutral player; and
- `KingdomAllianceObservation` for an existing tracked game Alliance/neutral game Alliance.

K4 never automatically creates roster/tracking/membership/transfer/diplomacy/contact state and never creates scores/rankings/recommendations.

### Existing business actions remain authoritative

Promotion must delegate through accepted K1/K3 recording contracts rather than direct writes, preserving Alliance ownership, current-Kingdom validation, stable-ID matching, append history, retry semantics, correction/invalidation, and disclosure boundaries.

### At-least-once processing must be safe

Source windows and normalized candidates use deterministic identity. Exact retries return the same ingestion result; later genuinely distinct captures remain distinct history.

### Quarantine before guesswork

Unsupported/malformed/ambiguous/out-of-context data is quarantined/rejected before business mutation. Candidate data is bounded operational scaffolding, not a general raw-source archive.

### No source-secret/raw-response archive

Kingdoms does not persist source passwords/API tokens/cookies/authorization headers/recovery material or canonical unbounded raw responses in tables, audit/outbox, logs, or candidate payloads.

## 4. In-scope capabilities

The initial K4 increment includes:

1. code/config allowlisted adapter contract;
2. Alliance-owned subscription lifecycle;
3. batch and normalized candidate persistence;
4. player-snapshot promotion for existing roster targets (`K4-P2`);
5. game-Alliance observation promotion for existing tracking targets (`K4-P3`);
6. scheduler/cursor/retry/replay (`K4-P4`);
7. manager review, observability, retention/revocation hardening (`K4-P5`); and
8. whole-increment acceptance (`K4-P6`).

`K4-P1` has delivered items 1–3 plus the manager foundation; items 4–8 remain gated.

## 5. Authorization and privacy

Normal member-safe promoted facts retain their existing `alliance.view` contracts. K4 subscription configuration/quarantine review/replay uses `kingdoms.manage` plus recent password confirmation. Background workers must carry/re-resolve explicit tenant/subscription/Kingdom context rather than impersonating a User.

Candidate/batch/subscription state is Alliance-owned. Current manager UI exposes bounded status/provenance and does not serialize normalized candidate payload bodies or source secrets.

## 6. Data and event ownership

| Concept | Ownership | Scope |
| --- | --- | --- |
| Adapter implementation/allowlist | Kingdoms + repository/operator config | Shared code/config |
| Subscription/batch/candidate | Kingdoms | Alliance-scoped |
| Neutral player/game-Alliance identity | Kingdoms | Global reference |
| Promoted snapshot/observation | Kingdoms | Alliance-scoped |
| Audit evidence | Audit | Alliance-correlated |
| Durable K4 events | Platform outbox | Alliance-scoped/internal |
| Current public API/webhook | Integrations | Unchanged; K4 excluded |

## 7. Delivery slices

- `K4-P0` — **Complete**: source/identity/tenancy/provenance/quarantine/automation contract lock.
- `K4-P1` / Slice A — **runtime validated**: adapter registry, subscriptions, batches/candidates, normalization/quarantine, deterministic identity, manager status/control, no promotion.
- `K4-P2` / Slice B — next after evidence-head gate: existing-roster player-snapshot promotion.
- `K4-P3` / Slice C — existing-tracking game-Alliance observation promotion.
- `K4-P4` / Slice D — scheduler/cursor/retry/replay/concurrency.
- `K4-P5` / Slice E — operations/review/retention/revocation hardening.
- `K4-P6` — whole-increment acceptance.

See the [implementation plan](kingdoms-automated-ingestion-implementation-plan.md) for gates.

## 8. Explicitly out of scope

K4 does not approve scraping/OCR/browser/game-client automation/bots/undocumented APIs; arbitrary manager URLs/headers/credentials; public inbound Kingdoms ingestion API/webhook; auto roster/tracking/membership/transfer/diplomacy/contact behavior; threat/desirability/punitive scoring/ranking/recommendations; `KINGDOMS-005` cross-Alliance sharing; or real-production source enablement/cutover without separate approval.

## 9. Acceptance rule

Each runtime slice must update living capability/security/operations/interfaces/testing truth and pass protected Dependency Review, CodeQL, complete CI, migration rollback/reapply where applicable, and slice-specific tenant/security/idempotency tests. The evidence/status head containing the validation record must also pass before the next slice begins.

Whole-increment acceptance occurs only at `K4-P6`. Real production source enablement and production cutover remain separate operational approval decisions.
