# Transactional outbox security review

[← Platform security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Platform  
**Capability:** Shared transactional outbox  
**Code owner:** `app/Domain/Platform`

## 1. Scope and security objective

Protect durable asynchronous event intent so producer state and its required outbox record commit atomically, tenant/private payloads remain minimized, at-least-once publication is safe to retry, and generic internal events cannot become public integrations without a separate Integrations eligibility decision.

## 2. Assets and sensitive data

Assets include outbox event type, owning tenant identity where applicable, safe producer payload, logical/idempotency key, occurrence/publication timestamps, attempts/availability lease, bounded error diagnostics, and request/trace correlation.

Outbox payloads can replicate tenant-private identifiers/data across domain boundaries. They must never become a generic place to copy passwords, MFA/recovery data, invitation/API/webhook secrets, private notes, or full sensitive model snapshots.

## 3. Trust boundaries

- Authorized producer transaction → `OutboxRecorder` persistence.
- PostgreSQL outbox → scheduled publisher claim/lease.
- Publisher → approved in-process/application listeners.
- Published source event → Notifications/Integrations downstream contracts.
- Integrations eligibility → external webhook fan-out; this is a separate boundary.

The outbox does not authorize producer actions or external exposure.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Business commit succeeds but event intent is lost | Missing security/business side effect | Required outbox row is written in same DB transaction as accepted producer state. |
| Transaction rolls back but event publishes | Ghost side effect | Rollback removes newly recorded outbox intent with business mutation. |
| Producer copies secrets/private narrative into payload | Broad data leakage | Producer owns safe/minimized payload semantics; generic outbox contract explicitly forbids secret/unnecessary private fields. |
| Missing/wrong tenant identity | Cross-tenant consumer action | Tenant-scoped events carry explicit tenant context; consumers do not infer hidden process state. |
| Duplicate publisher/consumer execution | Repeated side effect | At-least-once model is explicit; stable message/logical identity and consumer idempotency required. |
| Concurrent workers double-claim messages | Duplicate amplification | Bounded PostgreSQL row claiming/lease behavior and publication state. |
| Retry failure becomes silent or infinite | Data loss/availability impact | Attempts, bounded backoff/availability state, bounded `last_error`, scheduled recovery/diagnosis. |
| Internal event automatically becomes public webhook | External data leak | Integrations separately filters explicit external-event eligibility; outbox existence is insufficient. |
| Audit/outbox conflated | Evidence or delivery semantics corrupted | Audit remains separate attributable evidence; outbox is durable publication intent. |

## 5. Authorization, tenancy and privacy

Producer domains authorize business changes before recording outbox intent. Platform outbox code does not grant permission and cannot sanitize an unauthorized producer action into an authorized one.

Tenant identity is explicit for tenant-scoped messages. Producers expose only the minimum safe fields required by approved consumers; cross-tenant payload construction is a producer defect and must fail review/tests.

## 6. Integrity, replay and concurrency

Publisher execution is at-least-once. Repeated publisher runs must not corrupt publication state; consumers use stable message/logical identity to make duplicate delivery harmless.

Idempotent producer retries that create no new business transition do not fabricate duplicate logical messages. Legitimate repeated state transitions may create distinct messages as defined by producer semantics.

## 7. Secret and data lifecycle

Outbox rows retain only safe bounded payload/diagnostic data. Secret-bearing values from Identity, Memberships, Integrations, runtime configuration, or private domain narratives are excluded.

Retention/redaction is Platform-coordinated, but removing old outbox diagnostics must not rewrite producer business history or Audit evidence. Error text is bounded and must avoid raw request/credential payloads.

## 8. Abuse limits and failure behavior

Publisher queries/batches, retry/backoff, attempts, error detail, and scheduler overlap/single-server controls are bounded. Failure leaves recoverable unpublished/error state rather than requiring the original business mutation to be replayed.

Operators repair publisher/consumer causes instead of manually marking rows published or editing producer state as routine recovery.

## 9. Verification and evidence

Tests cover atomic business + outbox recording, rollback behavior, tenant propagation, deterministic/logical identity where required, repeated publisher execution, concurrent claim behavior, at-least-once consumer safety, failure/retry recovery, safe payload exclusions, and separation from Audit/Notifications/Integrations external eligibility.

Shared policy: [Security baseline](../../../security/security-baseline.md). Historical source: [Phase 1 threat model](../../../security/phase-1-threat-model.md) and Phase 6 platform hardening evidence.

## 10. Residual risks and external controls

A producer can still design an overbroad payload unless code review/tests enforce minimization; the generic outbox cannot understand every domain's privacy semantics. Consumer idempotency is also a distributed contract and must remain correct as listeners evolve.

Redis/worker/scheduler/database capacity and production monitoring are P3 operational evidence; they cannot be used to excuse missing P2 payload, tenant, secret, or external-eligibility controls.