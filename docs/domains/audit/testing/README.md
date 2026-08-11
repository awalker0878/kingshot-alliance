# Audit testing and evidence

[← Audit domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Audit  
**Code owner:** `app/Domain/Audit`  
**Primary validation boundary:** Attributable append-oriented audit evidence without authorization, transport, or secret leakage semantics  
**P5 evidence decision:** Living suite map with Phase 1 and later security evidence reused

## 1. Critical claims and validation ownership

Audit validation must prove that privileged/security-relevant producer actions create attributable evidence, that safe metadata is retained without credential/private-secret leakage, and that Audit remains evidence storage rather than a message bus or authorization service.

Producer domains own the business transition; Audit owns evidence semantics.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, and `Integration`. `TenantIsolation` applies where tenant-attributed audit evidence is exercised through cross-Alliance workflows. No dedicated Audit `Performance` threshold or direct UI suite is claimed.

## 3. Architecture and domain-boundary validation

Architecture evidence protects the `AuditRecorder` as the supported cross-domain write contract and prevents Audit from becoming an alternative persistence/transport path for feature domains.

The structural suite also protects current domain/security/operations/interfaces/testing ownership.

## 4. Authorization, tenancy, security and privacy validation

Owning feature tests prove authorization before audit recording. Audit-oriented integration evidence verifies actor/Alliance/subject attribution and safe metadata.

[Audit security](../security/README.md) defines secret/private-data exclusions. Testing should fail if credentials, MFA recovery material, webhook secrets, private diplomacy/contact text, or similarly prohibited data is introduced into routine audit payloads.

## 5. Feature, interface and integration validation

Audit has no direct HTTP API. Feature-domain workflows validate that supported mutations record evidence through the internal contract and that ordinary user-facing responses do not expose raw audit persistence.

[Audit interfaces](../interfaces/README.md) remains the current contract map.

## 6. Idempotency, concurrency and asynchronous validation

Audit append behavior is exercised alongside producer transactions. Outbox and Audit records are distinct: tests must not assume one automatically substitutes for the other.

Where repeat business transitions are legitimate, evidence identity follows the producer action rather than collapsing all repeated transitions into one audit row.

## 7. Persistence, migration, rollback and recovery evidence

Phase 1 introduced foundational audit persistence and accepted PostgreSQL migration/recovery evidence. Current CI reruns forward migration and backup/restore gates.

Retention/redaction/recovery behavior is documented in [Audit operations](../operations/README.md); direct destructive edits are not an accepted recovery test path.

## 8. Performance, query and capacity evidence

No standalone Audit query-budget or throughput SLA is accepted. Audit persistence participates in broader transaction/workflow tests. A new capacity claim requires explicit executable evidence before this profile may state a threshold.

## 9. Accessibility and frontend evidence

Audit exposes no current first-party standalone UI, so dedicated accessibility evidence is not applicable. Accessibility of feature workflows that trigger audit records remains owned by those feature domains.

## 10. Historical accepted evidence

Foundational evidence is [Phase 1 exit report](../../../product/phase-1-exit-report.md). Later phase/increment exit and security records demonstrate Audit reuse across Content, Events, Recruitment, Contributions, Platform, Integrations and Kingdoms without changing Audit ownership.

## 11. Evidence identity, retention and supersession

Historical phase/increment run IDs remain historical. Living Audit testing maps may evolve with current tests but must continue to point to executable evidence classes and safe-data expectations.

New acceptance evidence follows the exact SHA/workflow identity rules in [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Audit has no public API, standalone export, authorization decision surface, queue-consumer contract, or accessibility surface. P5 treats those as deliberate non-capabilities, not missing tests.

Related documentation:

- [Audit domain](../README.md)
- [Audit security](../security/README.md)
- [Audit operations](../operations/README.md)
- [Audit interfaces](../interfaces/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
