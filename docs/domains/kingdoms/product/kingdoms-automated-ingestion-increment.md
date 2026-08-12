# Kingdoms automated game-data ingestion product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** **Accepted** — `K4-P0`–`K4-P6` Complete  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; not historical Phase numbering or the completed DCP  
**Baseline dependency:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`  
**Implementation sequence:** [KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)  
**Exit evidence:** [KINGDOMS-004 exit report](kingdoms-automated-ingestion-exit-report.md)  
**Living capability:** [Automated game-data ingestion](../automated-ingestion.md)

## 1. Purpose

`KINGDOMS-004` reduces repetitive manual entry of factual Kingshot player/game-Alliance observations through explicitly approved machine-readable sources while preserving accepted Kingdoms identity, tenancy, history, privacy, and human-decision boundaries.

K4 is an ingestion boundary, not an acquisition loophole. It does not authorize scraping, OCR, browser/game-client automation, bots, undocumented/unapproved APIs, credential capture, arbitrary tenant URLs, or any source simply because it is technically reachable.

## 2. Current product state

The accepted generic repository increment provides:

- code/config adapter registry and Alliance/current-Kingdom subscriptions;
- bounded batches/candidates, deterministic source-window/candidate identity, quarantine/rejection and manager controls;
- existing-roster player-snapshot promotion through the accepted K1 recording contract;
- existing-active-tracking game-Alliance observation promotion through the accepted K3 recording contract;
- scheduled acquisition, opaque cursor, retry/backoff/circuit/concurrency and controlled replay;
- source-revocation reconciliation, bounded operational retention/pruning and payload-free health monitoring;
- realistic-volume aggregate-query evidence for the operations path; and
- a dedicated whole-increment acceptance scenario proving promotion, exact-window idempotency, revocation and canonical-history survival after operational pruning.

The **production adapter allowlist remains empty**, so repository acceptance does not itself acquire real Kingshot data or approve any provider. Whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468` with 429 tests / 9,799 assertions. See the [exit report](kingdoms-automated-ingestion-exit-report.md).

## 3. Core business rules

### Approved sources only

A runtime adapter must be explicitly registered/approved. Manager input cannot define arbitrary hosts/URLs/headers/credentials. A concrete source requires documented authorization/terms, transport/network controls, stable-ID/field contract, authentication/secret ownership, rate/timeout/cursor behavior, schema/version change handling, monitoring and revocation procedure before production enablement.

### Kingdoms owns ingestion semantics

Kingdoms owns source subscription state, batches/candidates, normalization, quarantine, promotion, operational retention and resulting game-observation provenance. Integrations continues to own current public read API/outbound webhooks; K4 creates no public Kingdoms machine contract or generic credential vault.

### Tenant and Kingdom context are explicit

Each subscription belongs to one platform Alliance and captures one Kingdom context. Workers/human actions re-resolve that context. Alliance-Kingdom drift preserves historical operational records but blocks new automated work instead of silently retargeting it.

### Stable game identifiers are the only automatic identity keys

Names, tags, handles, source row positions, and unproven source-local IDs never auto-match a player/game Alliance. Missing/ambiguous identity quarantines.

### Promotion is factual and existing-target-only

K4 promotion creates only factual `PlayerSnapshot` for an existing roster target and factual `KingdomAllianceObservation` for an existing active tracking target. It never automatically creates roster/tracking/membership/transfer/diplomacy/contact state and never creates scores/rankings/recommendations.

### Existing business actions remain authoritative

Promotion delegates through accepted K1/K3 recording contracts rather than direct writes, preserving Alliance ownership, current-Kingdom validation, stable-ID matching, append history, retry semantics, correction/invalidation boundaries and disclosure rules.

### At-least-once processing is safe

Source windows and normalized candidates use deterministic identity. Exact retries return the same ingestion result; later genuinely distinct captures remain distinct history. Cursor advances only after completed/partial work.

### Revocation fails closed

An active/paused subscription whose adapter key/version is no longer approved is disabled with bounded `source_unapproved` state. K4 never silently substitutes another source/version.

### Operational retention is not business-history retention

Candidate/batch/subscription state is bounded operational scaffolding. Promoted K1/K3 canonical history copies bounded provenance independently. Retention may redact/prune operational state but cannot delete promoted canonical observations or rewrite provenance.

### No source-secret/raw-response archive

Kingdoms does not persist source passwords/API tokens/cookies/authorization headers/recovery material or canonical unbounded raw responses in tables, audit/outbox, logs, candidate payloads or health output.

## 4. In-scope capabilities

The accepted K4 repository increment includes:

1. code/config allowlisted adapter contract;
2. Alliance-owned subscription lifecycle;
3. batch and normalized candidate persistence;
4. player-snapshot promotion for existing roster targets (`K4-P2`);
5. game-Alliance observation promotion for existing tracking targets (`K4-P3`);
6. scheduler/cursor/retry/replay (`K4-P4`);
7. manager review, observability, retention/revocation hardening (`K4-P5`); and
8. whole-increment acceptance (`K4-P6`).

All eight gates are complete and recorded in the implementation plan/exit report.

## 5. Authorization and privacy

Normal member-safe promoted facts retain existing `alliance.view` contracts. K4 subscription configuration/quarantine review/replay uses `kingdoms.manage` plus recent password confirmation. Background workers/maintenance carry or re-resolve explicit tenant/subscription/Kingdom context rather than impersonating a User.

Candidate/batch/subscription state is Alliance-owned. Manager and health surfaces expose bounded status/provenance/aggregate operational signals and do not serialize normalized candidate payload bodies or source secrets.

## 6. Data, retention and event ownership

| Concept | Ownership | Scope |
| --- | --- | --- |
| Adapter implementation/allowlist | Kingdoms + repository/operator config | Shared code/config |
| Subscription/batch/candidate | Kingdoms | Alliance-scoped operational state |
| Neutral player/game-Alliance identity | Kingdoms | Global reference |
| Promoted snapshot/observation | Kingdoms | Alliance-scoped canonical history |
| Audit evidence | Audit | Alliance-correlated |
| Durable K4 events | Platform outbox | Alliance-scoped/internal |
| Current public API/webhook | Integrations | Unchanged; K4 excluded |

Default operational windows are 30-day terminal payload redaction, 90-day terminal candidate/batch retention, 180-day quarantined-candidate retention, and 30-day disabled-subscription state compaction. Canonical promoted history remains independent.

## 7. Delivery slices

- `K4-P0` — **Complete**: source/identity/tenancy/provenance/quarantine/automation contract lock.
- `K4-P1` / Slice A — **Complete**: adapter registry, subscriptions, batches/candidates, normalization/quarantine, deterministic identity, manager status/control.
- `K4-P2` / Slice B — **Complete**: existing-roster player-snapshot promotion.
- `K4-P3` / Slice C — **Complete**: existing-tracking game-Alliance observation promotion.
- `K4-P4` / Slice D — **Complete**: scheduler/cursor/retry/replay/concurrency.
- `K4-P5` / Slice E — **Complete**: operations/review/retention/revocation/health/capacity hardening.
- `K4-P6` — **Complete / Accepted**: whole-increment acceptance.

See the [implementation plan](kingdoms-automated-ingestion-implementation-plan.md) and [exit report](kingdoms-automated-ingestion-exit-report.md).

## 8. Explicitly out of scope

K4 does not approve scraping/OCR/browser/game-client automation/bots/undocumented APIs; arbitrary manager URLs/headers/credentials; public inbound Kingdoms ingestion API/webhook; auto roster/tracking/membership/transfer/diplomacy/contact behavior; threat/desirability/punitive scoring/ranking/recommendations; `KINGDOMS-005` cross-Alliance sharing; or real-production source enablement/cutover without separate approval.

## 9. Acceptance rule

`KINGDOMS-004` acceptance is established by the protected whole-increment runtime candidate and the protected containing acceptance/evidence status head. Real production source enablement and production cutover remain separate operational approval decisions and require source-specific authorization, network, secret, schema/cursor, monitoring and rollback evidence.
