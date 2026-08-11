# Outbound webhooks security review

[← Integrations security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Integrations  
**Capability:** Signed outbound webhooks  
**Code owner:** `app/Domain/Integrations`

## 1. Scope and security objective

Protect outbound webhook subscriptions/delivery so only explicitly externally eligible source events for the source Alliance are sent, with protected signing material, bounded payloads, safe destination checks, and retry behavior that tolerates at-least-once delivery without exposing unrelated internal events.

## 2. Assets and sensitive data

Assets include subscription endpoint/event selectors/state, protected signing material, source outbox identity, webhook payload/envelope, delivery/attempt/retry state, response code, and bounded error diagnostics.

Signing material is secret. Payloads may contain tenant-private data and therefore require explicit event eligibility and field minimization before crossing the external boundary.

## 3. Trust boundaries

- Privileged Alliance manager → subscription create/revoke.
- Platform outbox publication → Integrations eligibility/fan-out.
- Integrations worker → external HTTPS endpoint.
- External receiver → raw-body signature verification.
- Retry/recovery scheduler → integrations queue/delivery state.
- Application endpoint validation → production DNS/routing/egress controls, which remain separate.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Wildcard subscription exposes every internal event | Private/internal contract leakage | Selector match is always intersected with explicit external-event eligibility. |
| Cross-tenant subscription receives source event | Data disclosure | Subscription and source message must belong to same Alliance. |
| Signing secret leaked through serialization/logs | Forged receiver trust / credential loss | Signing material protected at rest and omitted from routine serialization/logging/diagnostics. |
| Signature canonicalization mismatch | Receiver accepts altered payload or cannot verify | Signed input is timestamp + exact JSON body; receiver verifies raw body. |
| SSRF/internal-network endpoint | Infrastructure access | HTTPS/endpoint validation at application boundary plus mandatory production egress controls. |
| DNS rebinding/routing change after validation | Internal-network reachability | Explicit residual risk; production egress blocks metadata/private/management networks. |
| Oversize payload | Resource abuse/data overexposure | Payload safety bound fails before transport; no silent truncation/send. |
| Duplicate/replayed delivery | Duplicate external side effect | Logical identity = subscription + source message; stable delivery/attempt state; receivers must be idempotent. |
| Retry storm starves core queues | Availability impact | Dedicated `integrations` queue, bounded attempts/backoff, recovery scheduling and exhaustion state. |
| Kingdoms/internal events escape | Unexpected external disclosure | Current `kingdoms.*` and `alliance.kingdom_updated` remain excluded from generic external fan-out. |

## 5. Authorization, tenancy and privacy

Subscription administration requires active Alliance context, `alliance.manage`, and recent password confirmation for sensitive create/revoke. Fan-out uses the source message's Alliance; a subscription cannot choose another tenant at delivery time.

Producer domains own safe external representations. Integrations must not serialize arbitrary model state or private narrative simply because the source event contains an identifier.

## 6. Integrity, replay and concurrency

Each subscription/source-message pair has one logical delivery identity. Duplicate fan-out reuses that logical delivery; job uniqueness prevents routine concurrent work, while at-least-once processing still requires external receivers to tolerate duplicates.

Revocation prevents new deliveries. Retry exhaustion records a terminal failed state rather than unbounded spinning. Publisher/transport retry never replays the originating business mutation.

## 7. Secret and data lifecycle

Signing material is created/stored through the supported protected lifecycle and never exposed in normal diagnostics. Subscription revocation ends future use of the secret for new delivery work.

Delivery diagnostics retain only bounded response/error information. Payload retention must not become an indefinite duplicate of sensitive producer state.

## 8. Abuse limits and failure behavior

Endpoint checks, HTTPS requirement, connection/total timeout, payload size bounds, queue partitioning, bounded retry/backoff, and recovery cadence limit abuse/amplification.

Invalid endpoint, oversize payload, revoked subscription, ineligible event, or exhausted delivery fails closed and remains diagnosable without secret disclosure.

## 9. Verification and evidence

Tests cover tenant/source matching, wildcard + eligibility behavior, exact-body signature verification, protected signing material, payload bounds, endpoint safety checks, idempotent delivery identity, retry/recovery/exhaustion, revocation, and explicit Kingdoms/internal-event exclusions.

Shared policy: [Security baseline](../../../../security/security-baseline.md). Historical source: [Phase 6 threat model](../../../../security/phase-6-threat-model.md).

## 10. Residual risks and external controls

Application endpoint validation cannot fully prevent DNS rebinding or future routing changes. Production egress policy/firewall/DNS controls must independently block metadata, private, loopback where inappropriate, and management networks. Repository CI does not prove those controls.

Authorized webhook recipients can retain or misuse delivered data; payload minimization, documented contracts, subscription governance, and receiver-side secret handling remain required.