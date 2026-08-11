# Notifications security profile

[← Notifications domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary security boundary:** tenant/member-explicit durable coordination derived only from source-domain authorized state, with minimized outbox payloads

## 1. Security purpose and scope

Notifications protects durable reminder/report coordination from cross-tenant leakage, duplicate logical work, stale-source delivery, and payload over-collection. It does not own generic external messaging providers or webhook transport.

This profile covers Event reminder state/materialization/queueing and scheduled Contribution-report due-time coordination.

## 2. Assets and sensitive data

Assets include reminder rules/deliveries, source Event/occurrence/registration/member identifiers, report schedule occurrence/request identities, status/attempt timestamps, and bounded downstream payload data.

This coordination state is tenant private. It must not accumulate unrelated candidate/member narrative or secret material merely because it is passed through scheduler/outbox infrastructure.

## 3. Actors, authentication and authorization

Source domains authorize configuration: Events controls reminder-related Event state and Contributions controls report schedules. Notifications coordinates only persisted state that already carries the relevant Alliance/member/source identity.

Member-facing reminder reads remain active-Alliance/membership scoped. Background workers do not gain new business authority by executing a job.

## 4. Tenant and privacy boundaries

Every persisted coordination record carries explicit Alliance/source/member identity where applicable. No hidden process-global tenant context is used for scheduled or queued work.

Payloads are minimized to the data required by the downstream coordination contract; unrelated private feature data remains in its owning domain.

## 5. Trust boundaries and data flows

Material flows are Events/Contributions persisted state → scheduler materialization/due selection → Notifications persistence → Platform outbox → downstream first-party consumer. Redis/queue execution is a transport boundary, not a source of tenant or authorization truth.

Notifications does not send outbound webhooks; Integrations owns that external network boundary.

## 6. Threats, abuse cases and controls

Threats include cross-tenant coordination, duplicate reminders/report requests, stale registration eligibility, scheduler replay, concurrent due claiming, payload overexposure, secret leakage in logs/outbox, and retry storms becoming duplicate business actions.

Controls include explicit tenant/source identity, deterministic logical identities, source-state rechecks, concurrency-safe due claiming, persisted status, bounded retry/catch-up, payload minimization, and separation from generic transport.

## 7. Integrity, concurrency and idempotency

Scheduler execution is at-least-once and intentionally safe to rerun. Deterministic identities prevent repeated scheduler invocations from creating a second logical reminder/report request for the same due occurrence.

Persisted due/unpublished state supports catch-up after interruption. Source business actions are not replayed merely because notification coordination retries.

## 8. Secrets and credential handling

Notifications owns no provider/API/webhook/password/MFA credentials. Message/outbox/log payloads must not contain credentials, recovery material, signing secrets, or private data beyond the minimum coordination need.

Any future provider credential lifecycle would require its own security review rather than being hidden in this profile.

## 9. Destructive operations, retention and deletion

Cancellation/expiry/retention of coordination state follows source-domain and Platform lifecycle rules. Deleting a reminder delivery row must not be used to rewrite whether the source Event/registration occurred.

Platform may orchestrate retention/anonymization, while Events/Contributions remain semantic owners of their source facts.

## 10. Auditability, observability and evidence

Operators distinguish source eligibility/configuration, Notifications persisted state, scheduler execution, queue/outbox publication, and downstream completion. Bounded status/attempt/error diagnostics are preferable to raw private payload capture.

Tests cover tenant isolation, deterministic identities, repeated scheduler execution, concurrency/catch-up, source rechecks, and ownership boundaries. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

The current repository does not prove external mail/SMS/push provider privacy or delivery security because Notifications does not own such a provider integration. Redis/queue operational isolation remains shared runtime evidence.

Notifications does not provide generic external messaging, own webhook transport, infer tenant identity from process state, or use retries to re-run source-domain business mutations.

## 12. Focused reviews and related documentation

No focused living Notifications security review is required for the current coordination-only model.

- [Event reminders](../event-reminders.md)
- [Scheduled Contribution report coordination](../scheduled-report-coordination.md)
- [Events security profile](../../events/security/README.md)
- [Contributions security profile](../../contributions/security/README.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
