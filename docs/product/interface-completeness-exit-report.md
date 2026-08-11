# DCP-P4 interfaces, events, and integrations completeness exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Status:** Candidate — protected validation pending  
**Content candidate SHA:** `3ebd2ec3a25432baa636840911995be1a451f9c2`

## 1. Outcome

The DCP-P4 interface/event/integration content inventory is fully implemented and ready for protected candidate validation.

P4 does not advance to DCP-P5 until the exact candidate/evidence head passes protected Dependency Review, CodeQL, and the complete CI workflow, and the resulting final exit/status head also passes the same protected gate.

## 2. Standard adopted

P4 introduced [Interface documentation standard](interface-documentation-standard.md), defining:

- source-of-truth precedence for executable route/bootstrap/scheduler/provider code and tests;
- exactly one living `docs/domains/<domain>/interfaces/README.md` profile per canonical domain;
- a deterministic 12-section interface-profile format;
- a risk-based threshold for new focused interface contracts;
- a deterministic 10-section new-focused-contract format;
- explicit reuse of already-complete accepted P1 capability contracts instead of cosmetic duplication;
- public/member/manager/Platform-admin/external-machine/internal vocabulary;
- producer/outbox/internal-consumer/external-webhook ownership separation;
- API credential/scope/versioning rules;
- webhook envelope/signature/retry/eligibility requirements;
- command/job/scheduler discoverability;
- file/import/export/media version/format requirements; and
- high-signal P4 structural enforcement without generated endpoint dumps.

## 3. Frozen inventory result

The [Interface coverage matrix](interface-coverage-matrix.md) covers all 14 canonical domains plus all executable route sources and the bootstrap-managed readiness surface.

Implemented coverage:

- **14/14** living domain interface profiles;
- **2/2** new focused P4 interface contracts;
- all required accepted P1 capability contracts reused/indexed from owning profiles;
- the complete current `routes/*.php` inventory represented in the matrix;
- `bootstrap/app.php` and `/health/ready` represented;
- custom command/scheduler inventory represented;
- outbox/internal-consumer/external-eligibility inventory represented; and
- material file/import/export/media and external-machine contracts represented.

## 4. New focused interface contracts

P4 adds only two new focused contracts because these current outputs have independent compatibility-sensitive byte/schema semantics not fully isolated by the accepted P1 capability set.

### Contributions report exports

`docs/domains/contributions/interfaces/report-exports.md` documents:

- authenticated/verified active-Alliance manager access plus password confirmation;
- 10/minute export route throttling;
- explicit report version `phase5.v1`;
- exact ordered report columns;
- CSV MIME/filename semantics;
- SpreadsheetML XML served as `.xls`, explicitly **not** OOXML `.xlsx`;
- `X-Report-Version` and `X-Report-Checksum` response headers;
- `ContributionReportRun` evidence creation, row count, SHA-256 checksum, run identity and audit event; and
- the separation between manager export schema and Integrations `/api/v1/contributions` JSON.

### Events calendar exports

`docs/domains/events/interfaces/calendar-exports.md` documents:

- authenticated/verified active-Alliance access;
- fixed current/upcoming query horizon;
- exact CSV fields;
- iCalendar metadata, occurrence-based UID, UTC date/time representation, escaping and CRLF output;
- private/no-store response behavior;
- omission of registration/attendance/private Rally detail; and
- the explicit absence of a long-lived anonymous/public calendar bearer token.

## 5. Accepted focused contracts reused by P4

P4 deliberately retains and indexes accepted contracts rather than rewriting them to a new format:

- Content — `media.md`;
- Contributions — `event-reconciliation.md`;
- Events — `registration-and-attendance.md`;
- Identity — `mfa-and-recovery.md`;
- Integrations — `api.md`, `webhooks.md`;
- Kingdoms — `csv-migration.md` plus the accepted roster/snapshot/intelligence/transfer/Alliance-intelligence set;
- Memberships — `invitations.md`;
- Platform — `lifecycle-and-retention.md`, `transactional-outbox.md`; and
- Recruitment — `application-intake.md`.

This preserves one authoritative contract per independently deep capability while every P4 interface profile remains the deterministic interface inventory/navigation point.

## 6. HTTP/UI/API ownership result

P4 reconciled the complete executable route set:

- `routes/web.php`;
- `routes/api.php`;
- `routes/account.php`;
- `routes/contributions.php`;
- `routes/integrations.php`;
- `routes/kingdoms.php`;
- `routes/platform.php`;
- `routes/console.php`; and
- routing/middleware/readiness declarations in `bootstrap/app.php`.

Profiles document route **families and ownership**, not one row per endpoint. This is intentional: route files/tests remain exhaustive runtime truth while profiles explain caller classes, tenancy/authorization, material input/output, cross-domain ownership and significant non-capabilities.

Rallies is an important ownership example: current HTTP adapter methods are hosted by Event controllers/workspaces, but Rallies retains semantic ownership of Rally guidance/formations/groups/assignments/participation state and actions.

## 7. External machine API result

Integrations remains the sole accepted external machine API owner.

The current `/api/v1` read contract has exactly three scope families:

- `alliance:read` → `GET /api/v1/alliance`;
- `events:read` → `GET /api/v1/events`;
- `contributions:read` → `GET /api/v1/contributions`.

Credentials are Alliance bound, issued once in `ks_live_<12 hex>.<64 hex>` form, persisted as a SHA-256 verifier, and used to derive tenant context. The external API remains read-only and rate-limited; there is no caller-selected tenant identifier.

No public Kingdoms API scope/route is accepted.

## 8. Webhook/event/outbox result

P4 makes four different responsibilities explicit:

1. producer domain owns business-event meaning;
2. Platform owns durable transactional-outbox recording/publication;
3. registered internal consumers react to `OutboxPublished`; and
4. Integrations independently decides whether a published event is externally eligible and owns external delivery.

Current internal `OutboxPublished` consumers are:

- Notifications → `MarkEventReminderPublished`;
- Recruitment → `MarkRecruitmentCandidateJoined`; and
- Integrations → `QueueWebhookDeliveries`.

The external webhook envelope, HMAC-SHA256 signature, delivery headers, 256-KiB payload cap, endpoint revalidation, delivery idempotency and retry behavior remain in the accepted Integrations webhook contract.

`alliance.kingdom_updated` and all `kingdoms.*` event families remain externally ineligible even when a subscription requests `*`.

## 9. Commands, jobs and scheduled work

The P4 matrix now owns discoverability for all repository custom commands in `routes/console.php`, including Platform config/launch/admin/lifecycle/usage/outbox work, Content scheduled publication, Notifications Event/Contribution delivery coordination, Integrations webhook queue recovery, and Recruitment retention/anonymization.

Each owning profile distinguishes scheduler/operator entry points from end-user HTTP authorization and points to P3 operations documentation for recovery/reconciliation details.

## 10. File/import/export boundaries

P4 documents the material current file boundaries:

- Content private media screening/storage/public-branding use;
- Contributions CSV and SpreadsheetML report exports;
- Events CSV/iCalendar outputs;
- Kingdoms `kingdoms-roster.v1` CSV preview/commit/export;
- Platform privileged Alliance JSON export.

Significant version/token/schema details are explicit, including Kingdoms exact headers/UTF-8/no-NUL/1-MiB/500-row limits, Contributions `phase5.v1`, Membership/Recruitment 64-hex token shapes, and Events iCalendar UID/PRODID/time semantics.

## 11. Explicit non-capabilities preserved

P4 does not expand runtime scope. It explicitly preserves the absence of:

- public/write Kingdoms API/webhooks;
- generic externalization of every outbox event;
- Alliance/Events/Contributions write API;
- OAuth/user-delegated external tokens;
- anonymous/public Event calendar subscription tokens;
- public Recruitment candidate management/export API;
- Platform support impersonation;
- automated Kingshot scraping/OCR/bot/game ingestion; and
- CI-driven real-production approval.

## 12. Navigation and ownership

`docs/domains/README.md` now exposes contract, security, operations, and interface profiles for all 14 domains. The canonical path is deterministic:

```text
app/Domain/<Domain>/
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
```

Product navigation now identifies P1–P3 as complete and P4 as current, linking the P4 standard/matrix.

## 13. CI enforcement

`tests/Architecture/InterfaceDocumentationTest.php` verifies:

- exactly 14 canonical code domains and one current interface profile per domain;
- required profile metadata and 12-section ordering;
- the exact two-new-focused-contract inventory;
- new focused-contract metadata and 10-section ordering;
- required links/existence for reused accepted capability contracts;
- code-backed coverage for every current `routes/*.php` source in the frozen matrix;
- `bootstrap/app.php` and `/health/ready` inventory coverage; and
- domain-index navigation to the standard/matrix/all interface profiles.

Existing architecture tests continue to enforce repository-wide local Markdown links, file naming/ownership, domain roots, P1 contracts, P2 security profiles/reviews, and P3 operations profiles/runbooks.

## 14. Validation gate

Before this report becomes Complete:

- protected Dependency Review must pass;
- protected CodeQL must pass;
- main CI must pass, including Pint, PHPStan/Larastan, all architecture/feature/integration tests and repository-wide Markdown-link validation;
- immutable image build, ephemeral staging, backup/restore and image scan must pass where included by CI;
- exact validated candidate head/check identifiers must be recorded; and
- the final P4 exit/status evidence head must pass the same protected gate before P5 becomes authoritative.

Until then, the correct `continue` decision remains **finish DCP-P4**.
