# Integrations testing and evidence

[← Integrations domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Integrations  
**Code owner:** `app/Domain/Integrations`  
**Primary validation boundary:** Alliance-bound machine API credentials, read-only scopes/tenant binding, and signed webhook eligibility/delivery/retry semantics  
**P5 evidence decision:** Living suite map with hardened Phase 6 and current API/webhook evidence reused

## 1. Critical claims and validation ownership

Integrations validation must prove API credential issuance/verifier/expiry/revocation, fixed read scopes, tenant derivation, API row/rate bounds, webhook subscription policy, external eligibility, exact signature construction, delivery identity, retry/backoff/terminal failure and Kingdoms external exclusions.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`. Unit evidence is material for deterministic credential/signature/endpoint-policy behavior; Integration owns durable webhook/outbox/queue state.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Integrations as the sole accepted external machine API/webhook owner, fixed API scope vocabulary, no public Kingdoms routes/scopes, and the producer-domain/Platform-outbox/Integrations-externality separation.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers manager integration administration, password confirmation, API credential tenant binding, scope denial, inactive Alliance denial, endpoint private-address rejection and secret non-disclosure.

[Integrations security](../security/README.md) defines API/webhook trust boundaries that tests must preserve.

## 5. Feature, interface and integration validation

[Read-only API](../api.md) and [Outbound webhooks](../webhooks.md) are the accepted deep contracts. Feature tests protect `/api/v1` authentication/response boundaries; Integration tests protect outbox fan-out, delivery persistence, endpoint revalidation, headers/signatures and retry state.

[Integrations interfaces](../interfaces/README.md) maps the complete current boundary.

## 6. Idempotency, concurrency and asynchronous validation

Webhook delivery identity is deterministic per subscription/outbox message. Platform publication is at-least-once, so fan-out/delivery consumers must be duplicate-safe. Retry/backoff and terminal failure transitions are explicit state-machine behavior rather than generic queue semantics.

Wildcard subscription matching must still exclude all `kingdoms.*` events.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 6 exit report](../../../product/phase-6-exit-report.md) records Integrations schema/runtime acceptance and now includes P5-recovered implementation and final protected run identities. Current CI performs forward migrations and backup/restore; P3 [Integrations operations](../operations/README.md) owns delivery recovery/reconciliation.

Database restore does not retract external webhooks already delivered.

## 8. Performance, query and capacity evidence

Performance evidence is applicable to explicit API/webhook capacity, queue partition and bounded-row/work claims. `/api/v1/events` and `/api/v1/contributions` are bounded projections; webhook payloads have a 256-KiB cap.

No undocumented request-latency SLA is accepted.

## 9. Accessibility and frontend evidence

The first-party integration-management UI is covered by accepted [Phase 6 accessibility review](../../../product/phase-6-accessibility.md) and frontend/source guards where applicable. External machine API/webhook protocols do not have a browser accessibility contract.

`npm run check` remains frontend quality evidence only.

## 10. Historical accepted evidence

Primary historical evidence is [Phase 6 exit report](../../../product/phase-6-exit-report.md): implementation `d1969889ffa044cd7690f263ba9ef70c63a425cb` with DR `31235514849`, CodeQL `31235514858`, CI `31235514843`, plus final Phase 6 head `35979623d8231ee56b8fbcb75301e7e0732df0ca` with DR `31252682835`, CodeQL `31252682836`, CI `31252682853`.

Kingdoms K1–K3 evidence further protects webhook external-exclusion behavior.

## 11. Evidence identity, retention and supersession

Historical Phase 6 run identities remain immutable. Current Integrations validation tracks current code/tests and API/webhook contracts.

Future external-contract acceptance records require exact SHA/check identities under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

No write API, OAuth/delegated user tokens, inbound mutation webhook, public Kingdoms API/webhooks, or automatic exposure of every outbox event is accepted. No protocol performance SLA is claimed beyond executable bounds.

Related documentation:

- [Integrations domain](../README.md)
- [Read-only API](../api.md)
- [Outbound webhooks](../webhooks.md)
- [Integrations security](../security/README.md)
- [Integrations operations](../operations/README.md)
- [Integrations interfaces](../interfaces/README.md)
- [Platform testing](../../platform/testing/README.md)
- [Kingdoms testing](../../kingdoms/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
