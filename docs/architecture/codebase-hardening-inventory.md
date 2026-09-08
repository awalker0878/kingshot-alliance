# Codebase hardening ownership inventory

Baseline: `7e780521295e868005ecfee5bd38b33e8215ec49`.
Program: [Codebase hardening](../product/codebase-hardening-program.md).
Findings and actual audit progress: [delivery ledger](../product/codebase-hardening-delivery-ledger.md).

This is an inventory and target ownership map, not a claim that every listed path has passed a behavioral/security/scalability audit. Baseline counts exclude dependencies and include 1,486 application PHP files, 120 Vue components/pages, 20 route files, 35 command classes and 14 CI workflow files. Seven bounded contexts contain 43 top-level capabilities.

## Context capabilities and authority

| Context | Capabilities present at baseline | Authoritative responsibilities |
| --- | --- | --- |
| Accounts | Authentication, Credentials, EmailVerification, Identity, MultiFactorAuthentication, Profile, Registration, Security | Account identity, credentials/provider identities, verification, sessions, account security and lifecycle |
| GameWorld | GiftCodes, Governance, KingdomMaps, KingdomTransfers, Kingdoms, Players, Progression | Governor identity/lifecycle/placement; Kingdom facts/governance; transfer state; immutable progression/map facts; Gift Code catalogue, evidence/trust and per-Governor redemption |
| Alliance | Access, Content, Lifecycle, Membership, Recruitment | Alliance lifecycle, membership/rank/delegation, recruitment and content |
| Operations | Access, BattlePlans, Events, KingPerks, Participation, Polls, Rallies, Results, Rosters, TerritoryPlanning | Live event/participation/rally state, planning intent, accepted results/Bear Hunt, reminders and territory desired state |
| Intelligence | Access, Contributions, Diplomacy, Evidence, Ingestion, Observations, Roster, Sharing | Observed facts, evidence/provenance, contributions, ingestion and sharing; Governor progression observations remain distinct from GameWorld reference facts |
| Communications | Delivery | Recipient preferences/endpoints, logical inbox intent, delivery attempts, channel routing, digests, retry and idempotency |
| Platform | Administration, AllianceAdministration, DataGovernance, Integrations | Explicit platform authority, retention/deletion, API credentials, integrations and webhooks |

These boundaries remain appropriate for a modular monolith. No initial evidence justifies collapsing contexts or introducing another domain authority. Corrections below concern misplaced orchestration and duplicate operational registration.

## Composition, orchestration and infrastructure

Baseline Workflow packages: `AccountOnboarding`, `ExternalEventParticipation`, `KingdomGovernance`. They coordinate owner APIs through scalar identities and immutable values.

Baseline ReadModel packages: `AllianceAssistant`, `AllianceDashboard`, `AllianceGovernance`, `AnnouncementBroadcastManagement`, `BotCommands`, `CommandOverview`, `ContributionHistory`, `EventAnalysis`, `EventCalendar`, `EventHistory`, `EventManagement`, `EventTypeAdministration`, `ExternalActorConnections`, `GiftCodes`, `IntelligenceSignals`, `KingdomGovernance`, `KingdomIntelligence`, `KingdomSettings`, `NotificationDelivery`, `PlatformAdministration`, `ProductionLaunch`, `Progression`, `RecruitmentDiscovery`, `RecruitmentManagement`, `Roster`, `ScreenshotIntake`, `SharedKingdomIntelligence`, `Support`, `TerritoryPlanning`.

Shared infrastructure owns audit/outbox, observability, runtime checks and security mechanisms. `bootstrap/providers.php` is the explicit provider composition root. Route exposure is composed in bootstrap and owner providers; each of the 20 route files requires middleware/owner review. Namespace placement alone does not establish authorization correctness.

ReadModel inspection found `QueueOfficerBriefNotifications`, `QueueIntelligenceChangeNotifications`, their publishers and CLI adapters producing Communications writes under read-only packages. HARD-005 moves cross-owner notification orchestration to `Workflows/NotificationDelivery`; authorized recipient/fact projections remain ReadModels and delivery persistence remains Communications. ADR-0018 reconciles ADR-0016 with the read-only rule. Database-backed behavior verification remains tracked in the ledger.

## Entry points and duplicate execution

The baseline schedule has three registration sites: `routes/console.php`, `bootstrap/app.php` and `GiftCodesServiceProvider`. Four owner workloads are duplicated between bootstrap and console commands: event reminders, immediate delivery, Gift Code source reconciliation and source backfill. Other callback-only tasks must remain present when HARD-003 consolidates registration. Global scheduling belongs in one registry; thin commands belong with the Action/Query/Workflow they expose.

Source notification meaning stays with source owners/workflows. Communications does not import source-domain models to reconstruct authorization or business semantics. Queue-time authorization must be reviewed alongside execution-time revocation, retries and receipt delivery.

## Verification map

| Gate owner | Entry point/evidence |
| --- | --- |
| PHP quality | `composer.json`: validate/lock installation, Pint, PHPStan, parallel PHPUnit |
| Architecture | `tests/v3/Architecture`, `tests/v3/Architecture/verify.php`; strict PSR-4, syntax and boot routes in Architecture V3 workflow |
| Schema/transactions | CI fresh PostgreSQL installation, capability behavior and concurrency suites |
| Frontend | `package.json`: lint, Prettier, Vue/TypeScript, accessibility, documentation/localization/receipts, product language, territory contracts, build/chunks/budgets |
| Behavior/visual | Intelligence, Gift Code, King Perk and KingdomMaps workflow suites; Playwright visual regression |
| Security/dependencies | CodeQL, dependency review and Composer/npm advisory checks |
| Runtime/recovery | CI production image/staging/recovery/scan; route/command/scheduler boot and operational launch checks |

Baseline CI evidence: frontend ESLint passed then Prettier failed on `GovernorProgressionScreenshotIntake.vue`; PHP syntax/PSR-4/route boot/fresh schema passed in their jobs, but Pint failed on four progression-related PHP files and Architecture V3 failed two `ProgressionDatasetAbsenceBoundaryV3Test` assertions. Later gates were consequently skipped. HARD-004 tracks full reconciliation; HARD-006 and HARD-007 isolate these concrete failures. Never infer full PHPStan/PHPUnit success from skipped steps.

## Audit method and remaining coverage

For each capability, trace protected writes and reads, cross-context references, provider wiring, API/Assistant projections, scheduled/queued work, listeners and external adapters. Inspect scoped queries, indexes/cardinality, batches/cursors, transaction boundaries, network calls, retries, locks, idempotency and diagnostics. Record specific material defects as new HARD items. Track frontend/server semantics and update relevant current contracts/ADRs/operations documents with each remediation.

Repeat inventory/dependency/duplicate-authority/dead-code/scalability sweeps after major moves. No capability is marked audited by this inventory alone; the ledger coverage table is the continuation authority.
