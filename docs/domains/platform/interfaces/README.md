# Platform interfaces

[← Platform domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Platform  
**Code owner:** `app/Domain/Platform`  
**Primary boundary:** Cross-tenant Platform administration, readiness/CLI controls, lifecycle/export orchestration, and shared transactional-outbox publication  
**P4 inventory decision:** Focused contracts reused — `../lifecycle-and-retention.md`, `../transactional-outbox.md`

## 1. Boundary purpose and ownership

Platform owns cross-tenant administrative surfaces and shared platform infrastructure that cannot be modeled as ordinary Alliance permissions: Platform-administrator grants, Alliance lifecycle/plan/settings/feature/usage/export/legal-hold orchestration, readiness/launch/config checks, and the shared transactional outbox.

Feature domains retain semantic ownership of their business state. Platform orchestration may invoke supported feature-domain contracts but does not gain permission to bypass their safety invariants.

## 2. Surface inventory

`routes/platform.php` exposes a `/platform` route family protected by authenticated session, verified Identity, `platform.admin`, and recent password confirmation:

- administration dashboard;
- Platform administrator grant/revoke;
- Alliance provisioning;
- Alliance lifecycle `suspend|close|delete|restore`;
- ownership transfer;
- plan/settings/feature updates;
- usage capture;
- privileged Alliance JSON export, throttled 5/minute; and
- legal-hold placement/release.

`bootstrap/app.php` exposes repository-controlled `GET /health/ready`. Horizon access is also protected by verified Identity + MFA + active Platform administrator grant.

Operator CLI includes config/launch checks, administrator bootstrap, usage/lifecycle/retention, and outbox publication commands.

## 3. Callers, authorization and tenancy

Web Platform administration requires a distinct active Platform administrator grant plus verified Identity, MFA-backed session where required by the provider/auth rules, and recent password confirmation. Alliance roles do not grant Platform authority.

Cross-tenant Platform routes deliberately do not use `AllianceContext` as the source of authority. Target Alliance identifiers are explicit administrative targets resolved by Platform actions/services and feature-domain safety rules.

CLI bootstrap/maintenance commands are trusted operator surfaces and must be restricted by deployment/operator access rather than exposed to ordinary application users.

## 4. Input and validation contracts

Lifecycle operations accept only the explicit `suspend`, `close`, `delete`, and `restore` operation vocabulary. Plan/settings/features/ownership/legal-hold inputs pass owning Platform validation and cross-domain constraints.

`platform:admin:grant {email}` resolves an existing User by normalized email and does not create an account. Usage/account-deletion command limits are bounded by the command definitions.

Outbox recording takes producer-owned event type/Alliance/aggregate/payload/idempotency identity according to [Transactional outbox](../transactional-outbox.md).

## 5. Output and disclosure contracts

Platform administration returns cross-tenant operational/fleet/lifecycle/usage state only to protected administrators. Alliance export JSON is a privileged governance/disclosure surface, not a general member or external-machine API.

`/health/ready` exposes readiness status intended for infrastructure/health evaluation; it does not grant administrative access or disclose arbitrary domain data.

Outbox publication emits internal `OutboxPublished` events to registered in-process consumers. That event is an internal contract; Integrations independently determines external webhook eligibility.

## 6. Internal actions, queries and services

Supported Platform contracts include administrator-grant services/actions, lifecycle/retention/legal-hold/export/usage/entitlement/feature services, runtime configuration/launch-readiness validation, and outbox recorder/publisher infrastructure.

Feature domains consume Platform entitlements/settings/outbox services without moving their semantic ownership into Platform. Platform calls feature-domain supported contracts where lifecycle/export operations require domain-specific handling.

## 7. Events, outbox and cross-domain consumers

Platform owns durable outbox publication and `OutboxPublished`. `AppServiceProvider` currently dispatches each published message to three internal consumers:

- Notifications → `MarkEventReminderPublished`;
- Recruitment → `MarkRecruitmentCandidateJoined`; and
- Integrations → `QueueWebhookDeliveries`.

At-least-once publication means consumers must be idempotent. Integrations applies external eligibility after publication; internal events such as `kingdoms.*` may be published yet remain externally ineligible.

## 8. Commands, jobs and scheduled work

Material Platform/shared commands include:

- `app:config-check`;
- `app:launch-check {--json}`;
- `platform:admin:grant {email}`;
- `platform:capture-usage {--limit=500}` — scheduled hourly with `--limit=2000`;
- `platform:process-account-deletions {--limit=100}` — scheduled hourly;
- `platform:enforce-retention` — scheduled daily at 03:45; and
- `outbox:publish {--limit=100}` — scheduled every minute.

Framework queue pruning remains shared Operations authority. See [Platform operations](../operations/README.md).

## 9. Files, imports, exports and external dependencies

The Platform file/data-output boundary is privileged Alliance JSON export. Lifecycle/export redaction/retention semantics are owned by [Alliance lifecycle and retention](../lifecycle-and-retention.md).

Material dependencies include PostgreSQL, Redis/Horizon, scheduler/runtime configuration, feature-domain state, Identity assurance, and external deployment infrastructure. Platform does not own Content/Kingdoms/Contributions file formats.

## 10. Failure, idempotency, versioning and compatibility

Missing Platform grant/assurance or invalid lifecycle/target state fails closed. Destructive lifecycle operations respect ownership/legal-hold/deadline invariants rather than direct persistence shortcuts.

Outbox publication is at-least-once with durable message/idempotency state defined in [Transactional outbox](../transactional-outbox.md). A successful originating business transaction is not replayed merely because downstream publication is retried.

Platform admin route/CLI vocabularies are internal operational compatibility contracts; real-production approval remains separate external/accountable evidence and is not inferred from green CI.

## 11. Explicit non-capabilities

Platform does not:

- derive Platform-admin authority from Alliance roles;
- support generic user/support impersonation;
- own feature-domain business semantics;
- own Integrations API credentials/webhook delivery state;
- make every outbox event externally visible;
- provide payment processing; or
- automatically approve real production cutover from repository CI.

## 12. Focused contracts, evidence and related documentation

P4 reuses:

- [Alliance lifecycle and retention](../lifecycle-and-retention.md)
- [Transactional outbox](../transactional-outbox.md)

Related documentation:

- [Platform domain](../README.md)
- [Platform security](../security/README.md)
- [Platform operations](../operations/README.md)
- [Transactional outbox runbook](../operations/transactional-outbox.md)
- [Lifecycle retention runbook](../operations/lifecycle-retention.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
