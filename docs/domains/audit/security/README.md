# Audit security profile

[← Audit domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Audit  
**Code owner:** `app/Domain/Audit`  
**Primary security boundary:** attributable, tenant-aware, minimized evidence recorded only after the owning domain authorizes and accepts a transition

## 1. Security purpose and scope

Audit preserves trustworthy evidence of privileged/security-relevant business changes without becoming an authorization engine or a secondary copy of sensitive business state.

The security objective is to retain enough actor/tenant/action/correlation evidence for accountability and incident diagnosis while minimizing private or secret payload content.

## 2. Assets and sensitive data

Audit assets include action names, tenant/actor/subject identifiers, timestamps, request/trace correlation, and bounded metadata. Audit records can reveal privileged activity patterns and therefore are not ordinary public data.

Passwords, MFA/recovery material, API/invitation tokens, signing secrets, private keys, sensitive production payloads, and unnecessary private narrative do not belong in audit persistence.

## 3. Actors, authentication and authorization

Feature domains authorize their own operations before calling `AuditRecorder`. Audit recording never grants permission and must not be used to infer that a caller was authorized merely because an event exists.

Access to audit evidence is more restrictive than possession of a business object identifier and must preserve tenant/cross-tenant administrative boundaries.

## 4. Tenant and privacy boundaries

Alliance-scoped events carry explicit `alliance_id`; global/cross-tenant Platform events use the appropriate Platform context rather than masquerading as tenant events.

Metadata is data-minimized. Private manager notes, candidate answers, diplomacy contacts, blocker narratives, or other sensitive fields are referenced by safe identifiers when evidence requires attribution rather than copied wholesale.

## 5. Trust boundaries and data flows

The main boundary is owning domain transaction → `AuditRecorder` → PostgreSQL audit persistence. HTTP-originated records may also carry request/trace correlation from the request context.

Audit data may later be consumed by authorized operational/security review, but it is not automatically an Integrations/public API or webhook contract.

## 6. Threats, abuse cases and controls

Threats include secret leakage through metadata, cross-tenant attribution errors, fabricated duplicate evidence on idempotent retries, loss of actor/tenant correlation, unbounded payload capture, and treating audit action names as public event contracts.

Controls include explicit tenant/actor fields, bounded metadata, domain-owned event naming, same-transaction recording where required, no-op retry discipline, and exclusion of secret/private fields.

## 7. Integrity, concurrency and idempotency

Where a domain requires atomic audit evidence, the audit row participates in the same database transaction as the accepted state change. A retry that produces no new business transition must not fabricate a new audit event.

Append-oriented domain history remains owned by that domain; Audit describes the accepted transition rather than rewriting or replacing source history.

## 8. Secrets and credential handling

Audit owns no secret credential lifecycle. It must never persist plaintext or derived values that make passwords, MFA factors, recovery codes, API credentials, invitation bearer tokens, webhook signing material, application keys, or private keys easier to recover or replay.

Known secret-bearing domains should pass safe identifiers/status changes only.

## 9. Destructive operations, retention and deletion

Audit evidence is retained/redacted according to Platform/privacy lifecycle rules and legitimate evidence obligations. Feature workflows must not casually destructively edit audit history to make business history appear cleaner.

Account/tenant deletion may anonymize actor references where required while preserving the minimum evidence necessary for integrity, legal hold, or operational accountability.

## 10. Auditability, observability and evidence

Audit is itself the attribution evidence layer and should correlate with structured request/trace logs without duplicating sensitive log payloads. Tests should verify required privileged events, correct tenant/actor attribution, no duplicate no-op evidence, and exclusion of known sensitive fields.

The shared [Security baseline](../../../security/security-baseline.md) defines repository-wide logging, secret, retention, and production evidence boundaries.

## 11. Residual risks and explicit non-capabilities

Audit cannot prove an upstream domain performed the correct authorization unless that domain's executable policy/tests are correct. It also cannot substitute for operational logs, domain history, or immutable external compliance archives.

Audit does not authorize actions, deliver outbox messages, expose a public audit API, or retain raw secret/private payloads for convenience.

## 12. Focused reviews and related documentation

No focused living Audit security review is required at current complexity; this profile is the complete Audit security contract for P2.

- [Audit domain contract](../README.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
