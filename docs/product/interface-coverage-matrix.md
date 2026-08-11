# Interfaces, events, and integrations coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Inventory state:** Frozen — 100% content and candidate validation complete; final evidence-head validation pending

## 1. Purpose

This is the authoritative DCP-P4 code-backed inventory. It freezes the material boundaries that must remain discoverable and owned: HTTP/UI/API surfaces, internal cross-domain contracts, outbox/event consumers, custom commands/jobs/scheduled work, file/import/export boundaries, external integrations, and significant non-capabilities.

The governing format and ownership rules are in [Interface documentation standard](interface-documentation-standard.md). Candidate acceptance evidence is in [P4 exit report](interface-completeness-exit-report.md).

## 2. Executable route and bootstrap inventory

The executable route sources represented by the living interface profiles are:

- `routes/web.php` — public, Identity, Alliance/member/manager, Content, Events/Rallies, Memberships, Recruitment surfaces;
- `routes/api.php` — Integrations-owned `/api/v1` external machine reads;
- `routes/account.php` — Identity account-deletion surfaces;
- `routes/contributions.php` — Contributions member/manager/report/export surfaces;
- `routes/integrations.php` — Integrations first-party credential/webhook administration;
- `routes/kingdoms.php` — Kingdoms roster/import/transfer/game-Alliance/diplomacy surfaces;
- `routes/platform.php` — Platform-administrator cross-tenant surfaces;
- `routes/console.php` — custom CLI plus scheduler definitions; and
- `bootstrap/app.php` — route mounting, `/health/ready`, middleware aliases, request context, metrics, and security headers.

Framework `/up` remains shared runtime infrastructure. `/health/ready` is the repository-controlled Platform readiness surface.

## 3. External machine contracts

Integrations owns the only accepted external machine API family: `/api/v1`, rate-limited at 60 requests/minute by API credential identity where available.

| Method/path | Required scope | Represented owner | Contract owner |
| --- | --- | --- | --- |
| `GET /api/v1/alliance` | `alliance:read` | Alliances | Integrations |
| `GET /api/v1/events` | `events:read` | Events | Integrations |
| `GET /api/v1/contributions` | `contributions:read` | Contributions | Integrations |

The API is read-only and derives tenant context from the Alliance-bound credential.

Integrations also owns outbound signed HTTPS webhooks. Producer domains own event meaning; Platform owns outbox durability. `alliance.kingdom_updated` and every `kingdoms.*` event remain external-ineligible even for wildcard subscriptions.

## 4. Custom command and scheduled-work inventory

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

Framework queue pruning remains shared Operations authority.

## 5. Outbox/event consumer inventory

Platform owns `OutboxPublished` and durable publication. Current registered internal consumers are:

- Notifications → `MarkEventReminderPublished`;
- Recruitment → `MarkRecruitmentCandidateJoined`; and
- Integrations → `QueueWebhookDeliveries`.

Internal publication, internal consumption, and external webhook delivery remain separate contracts.

## 6. Frozen domain inventory

| Domain | Material P4 boundaries | P4 focused-contract decision | Status |
| --- | --- | --- | --- |
| Alliances | Alliance create/activate/overview; active tenant context; Alliance facts represented by Integrations | Profile only | Complete |
| Audit | internal `AuditRecorder`; no direct HTTP/API | Profile only | Complete |
| Authorization | permission/rank/evaluation/role assignment; Memberships route adapters | Profile only | Complete |
| Content | public/member/manage content, branding/media, scheduled publication | Reuse `media.md` | Complete |
| Contributions | records/reporting, Events reconciliation, API projection, privileged exports | `interfaces/report-exports.md`; reuse `event-reconciliation.md` | Complete |
| Events | calendar/detail/registration, Rally adapters, API projection, authenticated CSV/ICS | `interfaces/calendar-exports.md`; reuse `registration-and-attendance.md` | Complete |
| Identity | auth/reset/verification/profile/session/password-confirm/MFA/account deletion | Reuse `mfa-and-recovery.md` | Complete |
| Integrations | credential/webhook admin, `/api/v1`, bearer scopes, outbound webhooks | Reuse `api.md`, `webhooks.md` | Complete |
| Kingdoms | roster/history/intelligence/import/export/transfer/diplomacy; internal-only events | Reuse `csv-migration.md` plus accepted Kingdoms set | Complete |
| Memberships | membership/invitation admin; 64-hex invitation show/accept; role adapters | Reuse `invitations.md` | Complete |
| Notifications | internal reminder/report scheduler actions, outbox handoff/consumer | Profile only | Complete |
| Platform | `/health/ready`, `/platform`, Horizon/admin/CLI, lifecycle/export/outbox | Reuse `lifecycle-and-retention.md`, `transactional-outbox.md` | Complete |
| Rallies | formations/guidance/groups/assignments/participation through Event adapters | Profile only | Complete |
| Recruitment | public/invited intake, private pipeline, Membership handoff, retention/outbox consumer | Reuse `application-intake.md` | Complete |

## 7. New focused P4 contracts

Exactly two new focused contracts are required and complete:

1. [Contributions report exports](../domains/contributions/interfaces/report-exports.md) — `phase5.v1`, CSV/SpreadsheetML, exact fields/headers/evidence semantics.
2. [Events calendar exports](../domains/events/interfaces/calendar-exports.md) — authenticated CSV/iCalendar, UTC/UID/calendar metadata, no public bearer feed.

## 8. Accepted capability contracts reused by P4

- Content — `docs/domains/content/media.md`;
- Contributions — `docs/domains/contributions/event-reconciliation.md`;
- Events — `docs/domains/events/registration-and-attendance.md`;
- Identity — `docs/domains/identity/mfa-and-recovery.md`;
- Integrations — `docs/domains/integrations/api.md`, `docs/domains/integrations/webhooks.md`;
- Kingdoms — `docs/domains/kingdoms/csv-migration.md` and accepted Kingdoms capability set;
- Memberships — `docs/domains/memberships/invitations.md`;
- Platform — `docs/domains/platform/lifecycle-and-retention.md`, `docs/domains/platform/transactional-outbox.md`; and
- Recruitment — `docs/domains/recruitment/application-intake.md`.

Every owning interface profile indexes its reused contracts.

## 9. File/import/export inventory

Material file boundaries are:

- Content private media upload/screening/storage/public-branding presentation;
- Events authenticated upcoming-occurrence CSV and ICS;
- Contributions privileged CSV and SpreadsheetML XML served as `.xls`;
- Kingdoms `kingdoms-roster.v1` CSV preview/commit and member/management export; and
- Platform administrator Alliance JSON export.

There is no accepted public Recruitment candidate export or public Kingdoms data export/API.

## 10. Significant token/signature/version contracts

- Membership invitation route tokens: 64 hexadecimal characters, authenticated/email-bound acceptance.
- Recruitment invitation application tokens: 64 hexadecimal characters, hashed, unused/unexpired.
- API credentials: `ks_live_<12 hex>.<64 hex>`, fixed read scopes, hashed verifier.
- Webhook signatures: HMAC-SHA256 over `<unix timestamp>.<exact JSON body>` with `X-Kingshot-*` headers.
- Kingdoms CSV: `kingdoms-roster.v1`, exact header order, UTF-8/no-NUL, 1 MiB, 500 rows.
- Contributions report version: `phase5.v1`.
- Events ICS: stable `PRODID`, occurrence-based UID, UTC DTSTART/DTEND.

## 11. Caller boundary summary

- **Anonymous/public:** approved public Content/branding, Recruitment intake, invitation landing, guest Identity entry points.
- **First-party member:** active-Alliance member-safe feature surfaces.
- **First-party manager:** owning-domain privileged mutations plus password confirmation where required.
- **Platform administrator:** distinct verified/MFA-backed Platform grant; not Alliance-role derived.
- **External machine:** Integrations bearer API only.
- **Outbound external:** Integrations webhooks only.
- **Internal:** supported actions/queries/services/outbox events between domains.

## 12. Explicit repository-wide non-capabilities

Current runtime does not provide:

- public/write Kingdoms API or Kingdoms webhooks;
- generic exposure of every outbox event;
- Alliance/Events/Contributions write API;
- OAuth/user-delegated external API tokens;
- anonymous/public Event calendar tokens;
- public Recruitment candidate management/export API;
- Platform support impersonation;
- automated Kingshot scraping/OCR/bot/game ingestion; or
- CI-driven real-production approval.

## 13. P4 structural enforcement

`tests/Architecture/InterfaceDocumentationTest.php` enforces:

- 14/14 code-domain/interface-profile parity;
- current profile metadata and 12-section order;
- exact two-new-focused-contract inventory;
- focused-contract metadata and 10-section order;
- required reuse links;
- executable `routes/*.php` coverage in this matrix;
- `bootstrap/app.php` and `/health/ready`; and
- domain-index navigation.

Existing architecture tests continue to enforce repository-wide Markdown links and P1–P3 documentation gates.

## 14. Protected candidate evidence

Candidate/evidence head `66b2ca498ac89e550d3e718b174e07172e7409bd` passed:

- Dependency Review `31512996437` — success;
- CodeQL `31512996420` — success;
- CI `31512996421` — success, including 485 Pint files, PHPStan 345/345 with 0 errors, 381 tests / 8,290 assertions, immutable image, staging, backup/restore, and image scan.

## 15. P4 exit checklist

- [x] Interface documentation standard adopted.
- [x] Code-backed route/bootstrap inventory frozen.
- [x] Custom CLI/scheduler inventory frozen.
- [x] Outbox/internal-consumer/external-eligibility inventory frozen.
- [x] File/import/export/external-machine inventory frozen.
- [x] 14/14 living domain interface profiles implemented.
- [x] 2/2 new focused P4 interface contracts implemented.
- [x] Reused accepted focused capability contracts indexed.
- [x] Domain/product navigation normalized.
- [x] P4 architecture enforcement active.
- [x] Complete frozen-inventory ownership review completed.
- [x] Exact P4 candidate/evidence head passed protected Dependency Review, CodeQL and complete CI.
- [ ] Exact final P4 exit/status evidence head protected-green.

P4 content and candidate validation are complete. P5 remains blocked until the final evidence/status head passes the same protected gate.
