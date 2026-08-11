# Current capability matrix

[← Product and program documentation](README.md)

**Document type:** Current product/system navigation  
**Status:** Current  
**Architecture governance:** `DCP-P6`

This matrix identifies implemented capability, primary ownership, and explicit current non-capability. Code/tests remain authoritative for exact runtime; living domain contracts provide current semantics. The completed historical implementation plan remains baseline history. Real production cutover remains **not approved** under [Production launch approval](production-launch-approval.md).

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
| In-app Event reminder coordination | Implemented | Notifications + Events | [Notifications](../domains/notifications/README.md) |
| Recruitment intake/pipeline/review/decision/onboarding/retention | Implemented | Recruitment | [Recruitment](../domains/recruitment/README.md) |
| Contribution records/calculations/corrections/report/export | Implemented | Contributions | [Contributions](../domains/contributions/README.md) |
| Scheduled Contribution-report request coordination | Implemented | Notifications + Contributions | [Notifications](../domains/notifications/README.md) |
| Read-only Alliance API credentials and approved API reads | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Signed outbound webhooks/retries for eligible events | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Platform administration/lifecycle/entitlements/legal hold/retention/usage/outbox | Implemented | Platform | [Platform](../domains/platform/README.md) |
| First-class Kingdom reference and Alliance→Kingdom association | Accepted K1 | Kingdoms + Alliances | [Kingdoms](../domains/kingdoms/README.md) |
| Neutral player identity and Alliance-owned roster | Accepted K1 | Kingdoms | [Roster](../domains/kingdoms/roster.md) |
| Append-oriented player snapshots/current-stale-missing | Accepted K1 | Kingdoms | [Snapshots](../domains/kingdoms/snapshots.md) |
| Roster aggregates/data-quality/linkage/movement/7–30d trends | Accepted K1 | Kingdoms | [Roster intelligence](../domains/kingdoms/intelligence.md) |
| Controlled roster CSV preview/confirmation/export | Accepted K1 | Kingdoms | [CSV migration](../domains/kingdoms/csv-migration.md) |
| Transfer cycles/participants/destinations/groups/readiness/blockers/completion/handoff | Accepted K2 | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Neutral game-Alliance tracking/factual observations/corrections | Accepted K3 | Kingdoms | [Alliance intelligence](../domains/kingdoms/alliance-intelligence.md) |
| Human-maintained diplomacy/NAP history/private contacts | Accepted K3 | Kingdoms | [Alliance intelligence](../domains/kingdoms/alliance-intelligence.md) |
| Descriptive game-Alliance intelligence/bounded factual trends | Accepted K3 | Kingdoms | [Alliance intelligence](../domains/kingdoms/alliance-intelligence.md) |
| Automated-ingestion generic control plane: allowlist, tenant subscription/batch/candidate/quarantine/rejection/status | K4-P1 runtime validated; evidence-head gate pending | Kingdoms | [Automated ingestion](../domains/kingdoms/automated-ingestion.md) |
| Scheduled/background processing, transactional outbox, health/readiness, request/trace correlation | Implemented shared runtime | Platform + source/consumer domains | [Background processing](../operations/background-processing.md) |
| Immutable-image deployment/staging/backup-restore/rollback controls | Implemented repository controls | Shared Operations | [Operations](../operations/README.md) |

## Kingdoms increment status

| Scope | Status | Evidence |
| --- | --- | --- |
| `KINGDOMS-001` — roster intelligence | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-002` — transfer planning | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-003` — Alliance intelligence/diplomacy | **Accepted** | [Kingdoms product](../domains/kingdoms/product/README.md) |
| `KINGDOMS-004` — automated game-data ingestion | **In progress** — P0 Complete; P1 runtime validated; P2+ gated | [K4 scope/validation](../domains/kingdoms/product/kingdoms-automated-ingestion-increment.md) |

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| Neutral `Kingdom` / `KingdomPlayer` / `KingdomAlliance` | Reference data | Shared identity never grants cross-Alliance access to tenant-owned state. |
| Platform `Alliance` vs game `KingdomAlliance` | Distinct | Application tenant and neutral game reference are different concepts. |
| Legacy free-form Alliance Kingdom storage | Removed | Alliance persists canonical `kingdom_id`. |
| Kingdoms public API | **Not approved / not implemented** | No roster/snapshot/transfer/diplomacy/ingestion `/api/v1` scope/route. |
| Kingdoms public webhooks | **Not approved / not implemented** | `alliance.kingdom_updated` and all `kingdoms.*` events remain internally excluded from fan-out. |
| K4 production adapter allowlist | **Empty** | Generic foundation exists, but no real source can be configured by a normal production manager. |
| Automated external Kingshot acquisition | **Not approved / not implemented** | No source poller, scraper/OCR/bot/browser/game-client automation, or documented/unapproved API use. |
| Automated K4 player/game-Alliance observation promotion | **Not implemented yet** | Slice A stages/quarantines only; K4-P2/P3 own delegated promotion for existing targets. |
| K4 scheduler/cursor/retry/replay worker | **Not implemented yet** | K4-P4 owns background processing after both promotion paths validate. |
| Cross-Alliance/shared Kingdom intelligence | **Not approved / not implemented** | Tenant intelligence remains private; `KINGDOMS-005` is separate. |
| Transfer marketplace/eligibility/resource optimization/automatic execution | **Not approved / not implemented** | Transfer planning remains explicit human coordination. |
| Threat/desirability/punitive scoring or automated recommendations | **Not approved / not implemented** | Intelligence remains factual/descriptive. |
| Automatic diplomacy/negotiation | **Not implemented** | Diplomacy state changes only by explicit manager action. |
| Payment processing/billing | **Not implemented** | Plans/entitlements exist without payment-processing workflow. |
| Support impersonation | **Not implemented** | Platform administrators receive no impersonation capability. |
| Generic email/SMS/push transport owned by Notifications | **Not implemented** | Notifications coordinates persisted in-app work. |
| Central public webhook event schema registry/version | **Not currently implemented** | Integrations owns envelope/signature/eligibility; producers own event payload semantics. |
| Laravel Pulse recording | **Disabled** | Foundation exists; hosted recording awaits schema/access policy. |
| OpenTelemetry exporter | **Not configured in repository** | Request/trace correlation exists without repository-configured OTEL export. |
| Repository production hardening | **Accepted controls** | CI/build/staging/recovery/scan demonstrated. |
| Real production launch | **Not yet approved** | Repository evidence does not prove deployed ingress/egress/secrets/operators/alerts/capacity/managed dependency recovery. |

## K4-P1 validation pointer

Exact K4-P1 runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed Dependency Review `31533284318`, CodeQL `31533284195`, and CI `31533284398`, including Pint 509, PHPStan 363/363 zero errors, 407 tests / 9,466 assertions, immutable image, staging, backup/restore and scan.

The evidence/status head containing [Slice A validation](../domains/kingdoms/product/kingdoms-automated-ingestion-slice-a-validation.md) must independently pass the same protected gate before K4-P2 starts.

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
