# Audit domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Audit`  
**Primary authorization boundary:** domain-owned privileged operations decide when audit evidence is recorded; Audit records evidence and does not grant authority

## 1. Purpose and ownership

Audit owns attributable security/business audit-event recording. It provides a stable cross-domain evidence boundary for privileged and security-relevant changes without becoming the owner of the business state being changed.

`AuditEvent` is the persisted evidence record and `AuditRecorder` is the supported recording service. Feature domains decide which accepted business transitions require audit evidence and provide only the minimum appropriate metadata.

## 2. Scope

### In scope

- persisted audit events;
- actor/tenant/action attribution;
- request/trace correlation where available;
- bounded metadata appropriate to an audit record;
- recording support consumed by privileged domain actions; and
- preservation of attributable evidence across supported lifecycle operations.

### Out of scope

- authorizing the operation being audited;
- transactional-outbox delivery, owned by Platform;
- feature-domain business persistence;
- storing secrets/private recovery material in audit metadata; and
- treating audit records as a public integration event catalog.

## 3. Domain model

### Audit event

An audit event represents an attributable security or business change. Depending on context it carries the owning Alliance/tenant when applicable, actor identity when retained/appropriate, stable action/event name, target/context identifiers, timestamps, request/trace correlation, and bounded non-secret metadata.

Audit evidence is distinct from transactional outbox state:

- Audit answers **what privileged/security-relevant change happened and who/what context performed it**.
- Platform outbox answers **what durable asynchronous business event must be published after persistence**.

A single accepted business transition may produce both records in the same transaction, but they have different purposes and ownership.

## 4. Core invariants

1. Audit recording never grants authorization.
2. The business domain remains owner of the state described by an audit event.
3. Security-relevant/privileged accepted transitions are attributable where the actor is legitimately retained.
4. Alliance-scoped audit records carry tenant context; a shared global reference is never a substitute for `alliance_id`.
5. Audit metadata contains only the minimum evidence needed for accountability/diagnosis.
6. Secrets, credentials, recovery codes, signing secrets, private keys, bearer tokens, and other secret material never belong in audit metadata.
7. Private manager notes, blocker detail, diplomacy-contact text, and similar sensitive narrative must not be copied into generic audit payloads unless an explicitly approved evidence requirement says otherwise.
8. An idempotent retry that produces no new business transition must not fabricate duplicate audit evidence.
9. Audit event existence does not make an event externally webhook/API eligible.

## 5. Lifecycles and workflows

### Record a privileged transition

The owning domain validates tenant scope, authorization, invariants, and business mutation. When the transition succeeds, it records audit evidence—normally in the same transaction as the state change—and supplies safe identifiers/metadata.

Examples documented across the runtime include:

- Alliance creation/settings changes;
- invitation create/revoke/resend/acceptance;
- membership status/leave changes;
- role assignment/removal;
- Content/Event/Recruitment/Contribution/Integration/Kingdoms privileged mutations;
- Platform administrator/lifecycle changes; and
- accepted Kingdoms roster/snapshot/import/transfer/diplomacy mutations.

### Correlate with request/trace

When created in an HTTP context, audit evidence should preserve request/trace correlation so operators can connect the business/security event to structured application logs without storing sensitive request payloads.

### Retain evidence across correction/history flows

Append-oriented domains such as Kingdoms and Contributions retain historical/correction evidence rather than rewriting the past; audit evidence should describe the accepted transition and not attempt to replace domain-owned history.

## 6. Authorization and tenancy

Audit itself is not the permission engine. The owning domain must authorize first using its policy/permission/platform-grant model.

Alliance-scoped audit writes carry the active Alliance/tenant identity explicitly. Cross-tenant Platform operations use their Platform authority/context rather than pretending to be Alliance-scoped actions.

Read/access policy for audit evidence must remain more restrictive than simply possessing a business object identifier.

## 7. Cross-domain contracts

### Consumes

- **Identity** — actor identity and request assurance/correlation context when present.
- **Alliances** — Alliance tenant identity for tenant-scoped events.
- **Feature domains** — accepted action name, target/context IDs, and safe bounded metadata.

### Exposes

- `AuditRecorder` as the intentional cross-domain recording contract; and
- persisted `AuditEvent` evidence for authorized operational/security review.

Domains should use the supported recorder rather than introducing duplicate local audit persistence services.

## 8. Persistence and data ownership

Audit owns audit-event persistence only. It does not own the model/table referenced by an event.

Audit records are evidence and should be retained/redacted according to the documented platform/privacy lifecycle rather than casually destructively edited as feature data.

## 9. Events, outbox and integrations

Audit and outbox are parallel concerns. Platform owns `OutboxRecorder`/outbox publication; Audit owns attributable evidence.

An internal outbox event or audit action name is not automatically a public webhook event. External exposure remains governed by Integrations.

## 10. HTTP, UI and API surfaces

Audit is primarily a supporting domain rather than a general member-facing CRUD surface. Authorized administrative/operational views may surface audit evidence where product scope allows it.

There is no generic public audit API contract in the current runtime.

## 11. Background processing

Audit recording is normally synchronous with the accepted business transition so evidence cannot be lost between commit and a later best-effort job.

Operational retention/redaction may be coordinated by Platform where defined.

## 12. Failure, idempotency and concurrency

- Audit creation should participate in the business transaction where the accepted contract requires atomic evidence.
- Idempotent business retries should not create duplicate evidence when no new transition occurred.
- Bounded metadata prevents unbounded/sensitive payload copying.
- Tenant identifiers are explicit rather than inferred from process-global state.

## 13. Security and privacy

Audit records can reveal privileged activity and identifiers; access and payload design must respect least privilege, tenant isolation, and data minimization.

Never record:

- passwords;
- MFA/recovery secrets;
- API tokens/verifiers/signing secrets;
- invitation bearer tokens;
- private keys;
- sensitive production payload dumps; or
- private narrative fields unnecessary to the evidence purpose.

## 14. Observability and operations

Audit records should correlate with request IDs/traces where possible and support diagnosis alongside structured logs, not replace application/operations telemetry.

See [Observability](../../operations/observability.md), [Security baseline](../../security/security-baseline.md), and [Platform domain](../platform/README.md).

## 15. Testing and architecture enforcement

Tests should protect:

- audit creation for required privileged transitions;
- attribution/tenant scoping;
- no duplicate evidence on idempotent no-op retries;
- exclusion of known private/secret fields from audit metadata; and
- architecture rules preventing feature domains from inventing duplicate generic audit/outbox infrastructure.

## 16. Explicit non-capabilities

Audit does not:

- authorize business actions;
- own feature-domain state;
- deliver transactional-outbox messages;
- expose all internal events publicly; or
- store secret/recovery material as evidence.

## 17. Capability documents

No separate Audit capability files are required at present.

## 18. Related documentation

- [Authorization domain](../authorization/README.md)
- [Alliances domain](../alliances/README.md)
- [Platform domain](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Observability](../../operations/observability.md)
- [Domain boundary audit](../../product/domain-boundary-audit.md)
- [`app/Domain/Audit/README.md`](../../../app/Domain/Audit/README.md)
