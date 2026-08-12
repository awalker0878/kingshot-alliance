# Current capability matrix

[← Product and program documentation](README.md)

**Document type:** Current product/system navigation  
**Status:** Current  
**Architecture governance:** `DCP-P6`

This matrix identifies implemented capability, primary ownership, and explicit current non-capability. Code/tests remain authoritative for exact runtime; living domain contracts provide current semantics. Real production cutover remains **not approved** under [Production launch approval](production-launch-approval.md).

## Implemented capabilities

| Capability | Current state | Primary ownership | Living contract |
| --- | --- | --- | --- |
| Accounts/authentication/verification/password/session/MFA | Implemented | Identity | [Identity](../domains/identity/README.md) |
| Multi-Alliance tenancy/settings/active tenant context | Implemented | Alliances | [Alliances](../domains/alliances/README.md) |
| Membership/invitation lifecycle | Implemented | Memberships | [Memberships](../domains/memberships/README.md) |
| Alliance roles/permissions/evaluation | Implemented | Authorization | [Authorization](../domains/authorization/README.md) |
| Attributable privileged/security audit | Implemented | Audit | [Audit](../domains/audit/README.md) |
| Public/member content, revisions, publication, private media | Implemented | Content | [Content](../domains/content/README.md) |
| Event schedule/recurrence/registration/waitlist/attendance | Implemented | Events | [Events](../domains/events/README.md) |
| Rally guidance/formations/groups/assignments/participation | Implemented | Rallies | [Rallies](../domains/rallies/README.md) |
| Recruitment intake/pipeline/review/decision/onboarding/retention | Implemented | Recruitment | [Recruitment](../domains/recruitment/README.md) |
| Contribution records/calculations/corrections/report/export | Implemented | Contributions | [Contributions](../domains/contributions/README.md) |
| Read-only Alliance API credentials and approved API reads | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Signed outbound webhooks/retries for eligible events | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Platform administration/lifecycle/entitlements/legal hold/retention/usage/outbox | Implemented | Platform | [Platform](../domains/platform/README.md) |
| First-class Kingdom reference and Alliance→Kingdom association | Accepted K1 | Kingdoms + Alliances | [Kingdoms](../domains/kingdoms/README.md) |
| Neutral player identity / roster / snapshots / intelligence / CSV | Accepted K1 | Kingdoms | [Kingdoms](../domains/kingdoms/README.md) |
| Transfer planning/readiness/completion/handoff | Accepted K2 | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Neutral game-Alliance tracking/factual observations/diplomacy/private contacts/descriptive intelligence | Accepted K3 | Kingdoms | [Alliance intelligence](../domains/kingdoms/alliance-intelligence.md) |
| K4 generic ingestion control plane: allowlist, subscriptions, batches, candidates, quarantine/rejection/status | K4-P1 Complete | Kingdoms | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| K4 player observation promotion to existing roster relations | K4-P2 Complete | Kingdoms | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| K4 game-Alliance factual observation promotion to existing active tracking | K4-P3 Complete | Kingdoms | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| K4 generic scheduler/cursor/retry/circuit/replay around approved adapters | K4-P4 Complete | Kingdoms + shared Platform queue runtime | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| Shared scheduled/background processing, transactional outbox, health/readiness, request/trace correlation | Implemented shared runtime | Platform + source/consumer domains | [Background processing](../operations/background-processing.md) |
| Immutable-image deployment/staging/backup-restore/rollback controls | Implemented repository controls | Shared Operations | [Operations](../operations/README.md) |

## Kingdoms increment status

| Scope | Status | Evidence |
| --- | --- | --- |
| `KINGDOMS-001` — roster intelligence | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-002` — transfer planning | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-003` — Alliance intelligence/diplomacy | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-004` — automated game-data ingestion | **In progress** — P0–P4 Complete; P5 Current/selected pending transition-head validation; P6 gated | [K4 plan](../domains/kingdoms/product/kingdoms-automated-ingestion-implementation-plan.md) |

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| Neutral `Kingdom` / `KingdomPlayer` / `KingdomAlliance` | Reference data | Shared identity never grants cross-Alliance access to tenant-owned state. |
| Platform `Alliance` vs game `KingdomAlliance` | Distinct | Application tenant and neutral game reference are different concepts. |
| Kingdoms public API | **Not approved / not implemented** | No roster/snapshot/transfer/diplomacy/ingestion `/api/v1` scope/route. |
| Kingdoms public webhooks | **Not approved / not implemented** | `alliance.kingdom_updated` and all `kingdoms.*` events remain internally excluded from fan-out. |
| K4 production adapter allowlist | **Empty** | Generic control/promotion/scheduler mechanics exist, but no real source runs in default production state. |
| Real automated external Kingshot acquisition | **Not approved / not configured** | Generic acquisition interface exists; no approved production endpoint/credential/provider adapter is configured. |
| K4 player/game-Alliance observation promotion | **Implemented for existing owning-Alliance relationships only** | No name/tag matching or auto roster/tracking creation/reactivation; machine K3 correction/invalidation remains unavailable. |
| K4 scheduler/cursor/retry/replay | **K4-P4 Complete** | Dedicated bounded queue/scheduler/cursor/replay exists but has no real production source until separate approval. |
| K4 operational retention/metrics/source-revocation hardening | **Current P5 work** | P5 owns pruning, review, alerts/capacity evidence and source-revocation procedures. |
| Cross-Alliance/shared Kingdom intelligence | **Not approved / not implemented** | Tenant intelligence remains private; `KINGDOMS-005` is separate. |
| Transfer automation / automatic diplomacy / scoring recommendations | **Not approved / not implemented** | Human workflows remain authoritative. |
| Real production launch | **Not yet approved** | Repository evidence does not prove deployed source authorization, ingress/egress/secrets/operators/alerts/capacity/managed dependency recovery. |

## K4-P4 validation pointer

Exact K4-P4 runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed Dependency Review `31545866277`, CodeQL `31545866288`, and CI `31545866249`, including Pint 523, PHPStan 371/371 zero errors, 423 tests / 9,697 assertions, frontend/build, immutable image, staging, backup/restore and scan.

Repaired containing evidence head `3bf795e12a99a98c5ad71e570744743056cedd14` independently passed Dependency Review `31547224197`, CodeQL `31547224301`, and CI `31547224414`, including the complete frontend/PHP/documentation/container/staging/backup/scan chain. P4 is Complete.

The transition/status head selecting K4-P5 must independently pass the same protected gate before Slice E runtime is written.

## Architecture and ownership navigation

- [Current architecture/ADR index](../adr/README.md)
- [Cross-domain dependency map](cross-domain-dependency-map.md)
- [Shared glossary](glossary.md)
- [Domain index](../domains/README.md)
- [Repository structure audit](repository-structure-audit.md)
- [Domain boundary audit](domain-boundary-audit.md)
- [Security](../security/README.md)
- [Operations](../operations/README.md)

Domain-specific evidence belongs under `docs/domains/<domain>/`; top-level product/security/operations remain cross-domain/program-wide.
