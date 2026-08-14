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
| Generic allowlisted game-data ingestion control plane, promotion, scheduler/cursor/retry/replay, source revocation, retention and health | Accepted K4 | Kingdoms + shared Platform queue runtime | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| Directional same-Kingdom opt-in sharing of explicit safe game-Alliance current/history intelligence with bounded retention | Accepted K5 | Kingdoms | [Shared intelligence](../domains/kingdoms/shared-intelligence.md) |
| Shared scheduled/background processing, transactional outbox, health/readiness, request/trace correlation | Implemented shared runtime | Platform + source/consumer domains | [Background processing](../operations/background-processing.md) |
| Immutable-image deployment/staging/backup-restore/rollback controls | Implemented repository controls | Shared Operations | [Operations](../operations/README.md) |

## Kingdoms increment status

| Scope | Status | Evidence |
| --- | --- | --- |
| `KINGDOMS-001` — roster intelligence | **Accepted** | [K1 exit](../domains/kingdoms/product/kingdoms-roster-intelligence-exit-report.md) |
| `KINGDOMS-002` — transfer planning | **Accepted** | [K2 exit](../domains/kingdoms/product/kingdoms-transfer-planning-exit-report.md) |
| `KINGDOMS-003` — Alliance intelligence/diplomacy | **Accepted** | [K3 exit](../domains/kingdoms/product/kingdoms-alliance-intelligence-exit-report.md) |
| `KINGDOMS-004` — automated game-data ingestion | **Accepted** | [K4 exit](../domains/kingdoms/product/kingdoms-automated-ingestion-exit-report.md) |
| `KINGDOMS-005` — opt-in shared Kingdom intelligence | **Accepted** | [K5 exit](../domains/kingdoms/product/kingdoms-shared-intelligence-exit-report.md) |

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| Neutral `Kingdom` / `Player` / `KingdomAlliance` | Reference data | Shared identity never grants cross-Alliance access to tenant-owned state. |
| Platform `Alliance` vs game `KingdomAlliance` | Distinct | Application tenant and neutral game reference are different concepts. |
| Kingdoms public API | **Not approved / not implemented** | No roster/snapshot/transfer/diplomacy/ingestion/shared-intelligence `/api/v1` scope/route. |
| Kingdoms public webhooks | **Not approved / not implemented** | All `kingdoms.*` events remain internally excluded from fan-out. |
| K4 production adapter allowlist | **Empty** | Generic control/promotion/scheduler/retention/health mechanics are accepted, but no real source runs in default production state. |
| Real automated external Kingshot acquisition | **Not approved / not configured** | Generic acquisition interface exists; no approved production endpoint/credential/provider adapter is configured. |
| K4 player/game-Alliance observation promotion | **Accepted for existing owning-Alliance relationships only** | No name/tag matching or auto roster/tracking creation/reactivation; machine K3 correction/invalidation remains unavailable. |
| K4 scheduler/cursor/retry/replay and operational hardening | **Accepted K4** | Dedicated bounded queue/scheduler/cursor/replay, source-revocation reconciliation, retention and payload-free health exist but have no real production source until separate approval. |
| Cross-Alliance/shared Kingdom intelligence | **Accepted K5 under explicit narrow boundary** | Directional two-party same-Kingdom opt-in, explicit per-target grants, bounded safe current/history first-party reads, no recipient canonical copy/reshare, immediate fail-closed removal/revoke/drift, and bounded operational retention. |
| K5 player/roster/transfer/diplomacy/contact sharing | **Not approved / not implemented** | K5 shares only the accepted safe game-Alliance observation data class. |
| K5 public directory/API/webhook or transitive reshare | **Not approved / not implemented** | Sharing remains authenticated first-party, non-transitive and explicitly granted. |
| Transfer automation / automatic diplomacy / scoring recommendations | **Not approved / not implemented** | Human workflows remain authoritative. |
| Real production launch | **Not yet approved** | Repository evidence does not prove deployed source authorization, ingress/egress/secrets/operators/alerts/capacity/managed dependency recovery for a production environment. |

## Recent Kingdoms validation pointers

K4 whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`, including the full PHP/frontend/immutable-image/staging/backup-restore/scan chain.

K5 whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`: Pint 560 files, PHPStan/Larastan 394/394 zero errors, 452 tests / 10,322 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore, image scan and cleanup. The preceding P5→P6 transition head `0eb0444ee195fd3a09c9c9c07cdb2b1ddcb92873` independently passed Dependency Review `31572748561`, CodeQL `31572748558`, and CI `31572748595`.

Repository acceptance of K4/K5 does not approve a concrete production ingestion source, broader public exposure, additional K5 shared data classes, cross-Kingdom sharing, reshare, scoring/ranking/recommendations or automatic decisions.

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
