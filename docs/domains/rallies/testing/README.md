# Rallies testing and evidence

[← Rallies domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary validation boundary:** Formation composition, Rally guidance/groups/assignments/participation, Event-workspace adapters, and Alliance tenant isolation  
**P5 evidence decision:** Living suite map with Phase 3 accessibility and workflow evidence reused

## 1. Critical claims and validation ownership

Rallies validation must prove formation composition constraints, effective Rally guidance, Event-specific recommendations, group/slot/assignment integrity, participation recording, tenant-safe member/manager disclosure and the semantic ownership boundary where Event controllers adapt Rallies-owned actions.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. No standalone Rallies `Performance` threshold is accepted.

Unit evidence is material for formation/value composition; Feature/Integration evidence owns coordinator/member workflows and Event adapter collaboration.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Rallies as the owner of guidance/formations/groups/assignments/participation even though current HTTP adapter methods live in Event controllers/routes.

Events remains owner of occurrence/registration/attendance facts. P4/P5 architecture documentation guards preserve this ownership split.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers active-Alliance member saved formations, manager Rally mutations, password-confirmed privileged work, same-Alliance membership assignments and cross-Alliance occurrence/group/assignment denial.

[Rallies security](../security/README.md) defines private coordination/disclosure boundaries.

## 5. Feature, interface and integration validation

Feature tests cover saved formations and Event-workspace Rally presentation/mutations. Integration evidence covers Events occurrence context, Membership assignments, Authorization, Audit/outbox and participation state.

[Rallies interfaces](../interfaces/README.md) remains the current boundary map and explicitly documents the Event-controller adapter ownership distinction.

## 6. Idempotency, concurrency and asynchronous validation

Rally group/assignment/composition actions enforce owning value/action invariants and capacity/slot constraints. Repeated requests may not bypass same-Alliance or composition validation.

Rallies has no accepted background executor; shared outbox retries do not replay Rally mutations and do not trigger in-game automation.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 3 exit report](../../../product/phase-3-exit-report.md) records Events/Rallies migration rollback/reapply plus staging/recovery evidence. Current CI continues clean forward migrations and backup/restore.

Domain recovery semantics are documented in [Rallies operations](../operations/README.md).

## 8. Performance, query and capacity evidence

Rally groups/formations have bounded correctness constraints, but no standalone query-count, latency or throughput SLA is accepted. Any future numeric performance claim requires explicit `Performance` or equivalent executable evidence.

## 9. Accessibility and frontend evidence

[Phase 3 accessibility review](../../../product/phase-3-accessibility.md) and source guards cover Rally guidance, formations, groups, assignments and coordinator/member controls within Event workspaces.

Current `npm run check` protects frontend quality but does not replace deployment-specific accessibility validation.

## 10. Historical accepted evidence

Primary historical evidence is [Phase 3 exit report](../../../product/phase-3-exit-report.md), with validated technical head `ad1cbf3228f86dd915dbc82466d441f7aca0c475` and protected DR `31187575970`, CodeQL `31187578967`, CI `31187575503`.

## 11. Evidence identity, retention and supersession

Phase 3 SHAs/run IDs/counts remain historical. Current Rally testing follows current code/tests and this profile.

Future acceptance evidence must record exact revision/workflow identity under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Rallies has no public/external API, game-state import/scraping, automated in-game Rally execution, or Events ownership of Rally state. No standalone performance SLA is claimed.

Related documentation:

- [Rallies domain](../README.md)
- [Rallies security](../security/README.md)
- [Rallies operations](../operations/README.md)
- [Rallies interfaces](../interfaces/README.md)
- [Events testing](../../events/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
