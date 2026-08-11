# Interfaces, events, and integrations coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Inventory state:** Frozen — implementation/documentation normalization in progress

## 1. Purpose

This is the authoritative DCP-P4 code-backed inventory. It freezes the material boundaries that must be discoverable before P4 can become Candidate: HTTP/UI/API surfaces, internal cross-domain contracts, outbox/event consumers, custom commands/jobs/scheduled work, file/import/export boundaries, external integrations, and significant non-capabilities.

The governing format and ownership rules are in [Interface documentation standard](interface-documentation-standard.md).

## 2. Executable route and bootstrap inventory

P4 inspected the complete repository routing bootstrap. The executable route sources that must remain represented by domain interface profiles are:

- `routes/web.php` — public, Identity, Alliance/member/manager, Content, Events/Rallies, Memberships, Recruitment surfaces;
- `routes/api.php` — Integrations-owned `/api/v1` external machine reads;
- `routes/account.php` — Identity account-deletion surfaces;
- `routes/contributions.php` — Contributions member/manager/report/export surfaces;
- `routes/integrations.php` — Integrations first-party credential/webhook administration;
- `routes/kingdoms.php` — Kingdoms roster/import/transfer/game-Alliance/diplomacy surfaces;
- `routes/platform.php` — Platform-administrator cross-tenant surfaces;
- `routes/console.php` — custom CLI plus scheduler definitions; and
- `bootstrap/app.php` — route mounting, `/health/ready`, middleware aliases, request context, metrics, and security headers.

Framework `/up` health routing is shared runtime infrastructure. `/health/ready` is the repository-controlled Platform readiness surface.

## 3. External machine contracts

### Read-only API

Integrations owns the only accepted external machine API family: `/api/v1`, rate-limited at 60 requests/minute by API credential identity where available.

Accepted endpoints/scopes:

| Method/path | Required scope | Represented owner | Contract owner |
| --- | --- | --- | --- |
| `GET /api/v1/alliance` | `alliance:read` | Alliances | Integrations |
| `GET /api/v1/events` | `events:read` | Events | Integrations |
| `GET /api/v1/contributions` | `contributions:read` | Contributions | Integrations |

The API is read-only. Credential tenant context is derived from the Alliance-bound machine credential; there is no caller-selected tenant identifier.

### Outbound webhooks

Integrations owns the outbound signed HTTPS webhook contract. Source event meaning remains producer-domain owned; Platform owns outbox durability.

Externally eligible outbox events use the Integrations envelope/signature/delivery contract. `alliance.kingdom_updated` and all `kingdoms.*` event families are explicitly external-ineligible even for wildcard subscriptions.

## 4. Custom command and scheduled-work inventory

The current custom CLI contracts in `routes/console.php` are:

| Command | Owning boundary | Scheduled |
| --- | --- | --- |
| `about:phase` | Platform/program diagnostic | No |
| `app:config-check` | Platform/shared runtime validation | No |
| `app:launch-check {--json}` | Platform launch-readiness validation | No |
| `platform:admin:grant {email}` | Platform administrator bootstrap | Operator-only |
| `platform:capture-usage {--limit=500}` | Platform usage snapshots | Hourly (`--limit=2000`) |
| `platform:process-account-deletions {--limit=100}` | Platform/Identity lifecycle | Hourly |
| `platform:enforce-retention` | Platform retention/legal-hold lifecycle | Daily 03:45 |
| `integrations:queue-webhooks {--limit=100}` | Integrations webhook recovery/queueing | Every minute |
| `content:publish-scheduled {--limit=100}` | Content scheduled publication | Every minute |
| `events:sync-reminders {--limit=250}` | Notifications consuming Events facts | Every minute |
| `events:queue-reminders {--limit=100}` | Notifications reminder outbox handoff | Every minute |
| `contributions:queue-reports {--limit=50}` | Notifications consuming Contributions schedules | Every minute |
| `recruitment:purge-expired {--limit=100}` | Recruitment retention/anonymization | Daily 03:15 (`--limit=250`) |
| `outbox:publish {--limit=100}` | Platform transactional outbox | Every minute |

Framework queue-batch/failed-job pruning remains shared Operations authority and is not a new domain interface contract.

## 5. Outbox/event consumer inventory

Platform owns `OutboxPublished` and durable outbox publication. Producer domains own each recorded business event type.

Current registered internal consumers of `OutboxPublished` are:

- Notifications → `MarkEventReminderPublished`;
- Recruitment → `MarkRecruitmentCandidateJoined`; and
- Integrations → `QueueWebhookDeliveries`.

Integrations applies the independent external-webhook eligibility filter after outbox publication. Therefore internal publication, internal consumption, and external delivery are three distinct contracts.

## 6. Frozen domain inventory

| Domain | Material P4 boundaries | P4 focused-contract decision | Status |
| --- | --- | --- | --- |
| Alliances | Alliance create/activate/overview; active tenant-context resolver consumed by feature domains; Alliance facts represented by Integrations | Profile only | In progress |
| Audit | internal `AuditRecorder` append/evidence contract consumed by feature domains; no direct HTTP/API | Profile only | In progress |
| Authorization | internal permission/rank/evaluation/role-assignment contracts; role routes mediated through Memberships | Profile only | In progress |
| Content | public Alliance/content/branding reads; member/manage workspace; publication; private media/storage/scanner; scheduled publication | Reuse `content/media.md` | In progress |
| Contributions | member/manager records/reporting; Events reconciliation; read-only API representation; privileged CSV/SpreadsheetML exports; scheduled-report source contract | **Add `interfaces/report-exports.md`**; reuse `event-reconciliation.md` | In progress |
| Events | member calendar/detail/registration; manager coordination; Rally adapter surfaces; read-only API representation; authenticated CSV/ICS | **Add `interfaces/calendar-exports.md`**; reuse `registration-and-attendance.md` | In progress |
| Identity | registration/login/logout/reset/verification/profile/session/password confirmation/MFA/account deletion; assurance consumed by domains | Reuse `mfa-and-recovery.md` | In progress |
| Integrations | first-party credential/webhook administration; `/api/v1`; bearer scopes; outbound signed webhooks; integrations queue | Reuse `api.md`, `webhooks.md` | In progress |
| Kingdoms | authenticated roster/history/intelligence/import/export, transfer and diplomacy workspaces; strict CSV; internal-only Kingdoms events | Reuse `csv-migration.md` plus accepted Kingdoms capability set | In progress |
| Memberships | manager membership/invitation administration; 64-hex bearer invitation show/accept; role-adapter routes | Reuse `invitations.md` | In progress |
| Notifications | no direct HTTP; reminder/report scheduler actions; outbox handoff and `OutboxPublished` consumer | Profile only | In progress |
| Platform | `/health/ready`; high-privilege `/platform`; Horizon/admin/CLI; lifecycle/export; shared outbox event infrastructure | Reuse `lifecycle-and-retention.md`, `transactional-outbox.md` | In progress |
| Rallies | member formation and manager guidance/group/assignment/participation surfaces mediated by Event controllers; internal Rally actions/models | Profile only | In progress |
| Recruitment | public/invitation application intake; private manager pipeline; Membership onboarding handoff; retention command; outbox consumer | Reuse `application-intake.md` | In progress |

## 7. Required new focused P4 contracts

P4 requires exactly two **new** focused interface contracts because the existing P1 capability set does not fully isolate these compatibility-sensitive output formats:

1. `docs/domains/contributions/interfaces/report-exports.md`
   - privileged CSV and SpreadsheetML export contract;
   - `phase5.v1` report-version semantics;
   - exact columns, MIME/filename/response evidence headers; and
   - export-run/checksum/audit behavior.
2. `docs/domains/events/interfaces/calendar-exports.md`
   - authenticated upcoming-event CSV contract;
   - authenticated iCalendar response contract;
   - UTC/UID/calendar metadata semantics; and
   - explicit absence of a public long-lived calendar bearer token.

## 8. Accepted capability contracts reused by P4

P4 intentionally reuses the following current living contracts rather than creating duplicate interface documents:

- Content — `docs/domains/content/media.md`;
- Contributions — `docs/domains/contributions/event-reconciliation.md`;
- Events — `docs/domains/events/registration-and-attendance.md`;
- Identity — `docs/domains/identity/mfa-and-recovery.md`;
- Integrations — `docs/domains/integrations/api.md`, `docs/domains/integrations/webhooks.md`;
- Kingdoms — `docs/domains/kingdoms/csv-migration.md` and the accepted roster/snapshot/intelligence/transfer/Alliance-intelligence capability set;
- Memberships — `docs/domains/memberships/invitations.md`;
- Platform — `docs/domains/platform/lifecycle-and-retention.md`, `docs/domains/platform/transactional-outbox.md`; and
- Recruitment — `docs/domains/recruitment/application-intake.md`.

Each owning P4 interface profile must link the reused contract explicitly.

## 9. File/import/export inventory

Material file boundaries are:

- Content private media upload/screening/storage/public-branding presentation — Content `media.md`;
- Events authenticated upcoming-occurrence CSV — new Events focused interface contract;
- Events authenticated ICS — new Events focused interface contract;
- Contributions privileged CSV — new Contributions focused interface contract;
- Contributions privileged SpreadsheetML XML served as `.xls` — new Contributions focused interface contract;
- Kingdoms `kingdoms-roster.v1` CSV import preview/commit and member/management export — Kingdoms `csv-migration.md`;
- Platform administrator Alliance JSON export — Platform lifecycle/retention profile/capability contract.

There is no accepted public Recruitment candidate export or public Kingdoms data export/API.

## 10. Significant token/signature/version contracts

P4 preserves these implementation-backed compatibility/security boundaries:

- Membership invitation route tokens: 64 hexadecimal characters; acceptance remains authenticated/email-bound under `invitations.md`.
- Recruitment invitation-mode application tokens: 64 hexadecimal characters, hashed at rest, unused and unexpired.
- API credentials: one-time `ks_live_<12 hex>.<64 hex>` bearer format, fixed read scopes, hashed verifier persistence.
- Webhook signatures: HMAC-SHA256 over `<unix timestamp>.<exact JSON body>` with `X-Kingshot-*` headers.
- Kingdoms CSV schema: `kingdoms-roster.v1`, exact header order, UTF-8/no-NUL, 1 MiB, 500-row maximum.
- Contributions report version: `phase5.v1`.
- Events ICS: stable `PRODID`, occurrence-based UID, UTC DTSTART/DTEND representation.

## 11. Public/member/manager/admin boundary summary

- **Anonymous/public:** home, approved public Content/branding, Recruitment intake, invitation landing page; authentication/account-recovery entry points where designed for guests.
- **First-party member:** active-Alliance overview/content/events/roster/etc. according to owning permissions and safe payloads.
- **First-party manager:** owning-domain management mutations plus recent password confirmation where required.
- **Platform administrator:** separate verified/MFA-backed grant plus password confirmation for `/platform`; not derived from Alliance roles.
- **External machine:** Integrations bearer API only.
- **Outbound external:** Integrations webhooks only.
- **Internal:** supported actions/queries/services/outbox events consumed between domains.

## 12. Explicit repository-wide non-capabilities relevant to P4

Current runtime does **not** provide:

- public/write Kingdoms API or Kingdoms webhook contracts;
- generic exposure of all outbox events as webhooks;
- a write API for Alliance/Events/Contributions;
- OAuth or user-delegated external API tokens;
- anonymous/public Event calendar subscription tokens;
- public Recruitment candidate management/export API;
- support impersonation through Platform administration;
- automated Kingshot scraping/OCR/bot/game-ingestion interfaces; or
- an interface by which CI automatically approves real production cutover.

## 13. P4 structural enforcement target

`tests/Architecture/InterfaceDocumentationTest.php` will enforce:

- 14/14 code-domain/interface-profile parity;
- current profile metadata and 12-section order;
- exact two-new-focused-contract inventory;
- focused-contract metadata and 10-section order;
- required reuse links to accepted capability contracts;
- executable `routes/*.php` coverage in this matrix;
- bootstrap route/health inventory coverage;
- domain-index navigation to all interface profiles; and
- repository-wide Markdown link integrity through the existing architecture gate.

## 14. P4 exit checklist

- [x] Interface documentation standard adopted.
- [x] Code-backed route/bootstrap inventory frozen.
- [x] Custom CLI/scheduler inventory frozen.
- [x] Outbox/internal-consumer/external-eligibility inventory frozen.
- [x] File/import/export/external-machine inventory frozen.
- [ ] 14/14 living domain interface profiles implemented.
- [ ] 2/2 new focused P4 interface contracts implemented.
- [ ] Reused accepted focused capability contracts indexed from owning profiles.
- [ ] Domain/product navigation normalized.
- [ ] P4 architecture enforcement active.
- [ ] Complete inventory/link review passes.
- [ ] Exact P4 candidate head passes protected Dependency Review, CodeQL and complete CI.
- [ ] P4 exit/status evidence finalized and exact final head protected-green.

P4 is **In progress**, not Candidate. P5 remains blocked.
