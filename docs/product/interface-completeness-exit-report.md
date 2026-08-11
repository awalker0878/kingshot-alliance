# DCP-P4 interfaces, events, and integrations completeness exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Status:** Complete candidate — final evidence/status head validation pending  
**Content candidate SHA:** `3ebd2ec3a25432baa636840911995be1a451f9c2`  
**Validated candidate/evidence SHA:** `66b2ca498ac89e550d3e718b174e07172e7409bd`

## 1. Outcome

The DCP-P4 interface/event/integration inventory is fully implemented and the exact candidate/evidence head passed all protected candidate gates.

P4 may be recorded as Complete and P5 selected in the final status ledger, but that transition is not authoritative until the exact resulting final evidence/status head independently passes protected Dependency Review, CodeQL, and complete CI.

## 2. Standard adopted

P4 introduced [Interface documentation standard](interface-documentation-standard.md), defining:

- source-of-truth precedence for executable route/bootstrap/scheduler/provider code and tests;
- exactly one living `docs/domains/<domain>/interfaces/README.md` profile per canonical domain;
- deterministic 12-section interface profiles and 10-section new focused contracts;
- explicit reuse of already-complete accepted P1 capability contracts instead of cosmetic duplication;
- public/member/manager/Platform-admin/external-machine/internal vocabulary;
- producer/outbox/internal-consumer/external-webhook ownership separation;
- API credential/scope/versioning rules;
- webhook envelope/signature/retry/eligibility requirements;
- command/job/scheduler discoverability;
- file/import/export/media version/format requirements; and
- high-signal P4 structural enforcement without generated endpoint dumps.

## 3. Frozen inventory result

The [Interface coverage matrix](interface-coverage-matrix.md) covers all 14 canonical domains, every executable `routes/*.php` source, and the bootstrap-managed readiness boundary.

Accepted content coverage:

- **14/14** living domain interface profiles;
- **2/2** new focused P4 interface contracts;
- all required accepted P1 capability contracts reused/indexed from owning profiles;
- complete current route/bootstrap inventory;
- custom command/scheduler inventory;
- outbox/internal-consumer/external-eligibility inventory; and
- material file/import/export/media and external-machine contracts.

## 4. New focused interface contracts

### Contributions report exports

`docs/domains/contributions/interfaces/report-exports.md` records:

- authenticated/verified active-Alliance manager access plus password confirmation;
- 10/minute export throttling;
- explicit report version `phase5.v1`;
- exact ordered report columns;
- CSV MIME/filename semantics;
- SpreadsheetML XML served as `.xls`, explicitly **not** OOXML `.xlsx`;
- `X-Report-Version` and `X-Report-Checksum` response headers;
- `ContributionReportRun` evidence/checksum/run identity/audit behavior; and
- separation from Integrations `/api/v1/contributions` JSON.

### Events calendar exports

`docs/domains/events/interfaces/calendar-exports.md` records:

- authenticated/verified active-Alliance access;
- fixed current/upcoming query horizon;
- exact CSV fields;
- iCalendar metadata, occurrence-based UID, UTC date/time representation, escaping and CRLF output;
- private/no-store behavior;
- omission of registration/attendance/private Rally detail; and
- no long-lived anonymous/public calendar bearer token.

## 5. Accepted focused contracts reused by P4

P4 retains and indexes accepted contracts rather than rewriting them:

- Content — `media.md`;
- Contributions — `event-reconciliation.md`;
- Events — `registration-and-attendance.md`;
- Identity — `mfa-and-recovery.md`;
- Integrations — `api.md`, `webhooks.md`;
- Kingdoms — `csv-migration.md` plus accepted roster/snapshot/intelligence/transfer/Alliance-intelligence contracts;
- Memberships — `invitations.md`;
- Platform — `lifecycle-and-retention.md`, `transactional-outbox.md`; and
- Recruitment — `application-intake.md`.

## 6. HTTP/UI/API ownership result

P4 reconciled:

- `routes/web.php`;
- `routes/api.php`;
- `routes/account.php`;
- `routes/contributions.php`;
- `routes/integrations.php`;
- `routes/kingdoms.php`;
- `routes/platform.php`;
- `routes/console.php`; and
- routing/middleware/readiness declarations in `bootstrap/app.php`.

Profiles document route families/ownership rather than generated endpoint lists. Runtime route files/tests remain exhaustive truth.

Rallies remains the semantic owner of Rally guidance/formations/groups/assignments/participation even though current HTTP adapters are hosted in Event controllers/workspaces.

## 7. External machine API result

Integrations remains the sole accepted external machine API owner. The current read-only `/api/v1` contract has exactly:

- `alliance:read` → `GET /api/v1/alliance`;
- `events:read` → `GET /api/v1/events`;
- `contributions:read` → `GET /api/v1/contributions`.

Credentials are Alliance bound, issued once in `ks_live_<12 hex>.<64 hex>` form, persisted as a SHA-256 verifier, and derive tenant context. No public Kingdoms API scope/route is accepted.

## 8. Webhook/event/outbox result

P4 makes four responsibilities explicit:

1. producer domain owns business-event meaning;
2. Platform owns durable transactional-outbox recording/publication;
3. registered internal consumers react to `OutboxPublished`; and
4. Integrations independently owns external eligibility/delivery.

Current internal consumers are Notifications `MarkEventReminderPublished`, Recruitment `MarkRecruitmentCandidateJoined`, and Integrations `QueueWebhookDeliveries`.

`alliance.kingdom_updated` and all `kingdoms.*` events remain externally ineligible even for wildcard subscriptions. The accepted Integrations webhook contract retains envelope, HMAC-SHA256 signature, headers, 256-KiB bound, endpoint revalidation, delivery identity and retry behavior.

## 9. Commands, jobs and scheduled work

The P4 matrix now makes all custom `routes/console.php` commands discoverable, covering Platform runtime/lifecycle/outbox controls, Content publication, Notifications Event/Contribution delivery coordination, Integrations webhook queueing, and Recruitment retention/anonymization.

P3 operations documentation remains authoritative for recovery/reconciliation behavior.

## 10. File/import/export boundaries

P4 documents:

- Content private media screening/storage/public-branding use;
- Contributions CSV and SpreadsheetML report exports;
- Events CSV/iCalendar outputs;
- Kingdoms `kingdoms-roster.v1` CSV preview/commit/export; and
- Platform privileged Alliance JSON export.

Compatibility-sensitive details include Kingdoms exact headers/UTF-8/no-NUL/1-MiB/500-row constraints, Contributions `phase5.v1`, Membership/Recruitment 64-hex tokens, and Events UID/PRODID/UTC semantics.

## 11. Explicit non-capabilities preserved

P4 does not expand runtime scope. It preserves no:

- public/write Kingdoms API/webhooks;
- generic externalization of every outbox event;
- Alliance/Events/Contributions write API;
- OAuth/user-delegated external tokens;
- anonymous Event calendar bearer feeds;
- public Recruitment candidate management/export API;
- Platform support impersonation;
- automated Kingshot scraping/OCR/bot/game ingestion; or
- CI-driven real-production approval.

## 12. Navigation and ownership

`docs/domains/README.md` exposes contract, security, operations, and interface profiles for all 14 domains. The deterministic path is:

```text
app/Domain/<Domain>/
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
```

## 13. CI enforcement

`tests/Architecture/InterfaceDocumentationTest.php` verifies:

- 14/14 code-domain/interface-profile parity;
- profile metadata and 12-section ordering;
- exact two-new-focused-contract inventory;
- new focused metadata and 10-section ordering;
- required reused-contract links/existence;
- code-backed coverage for every current `routes/*.php` source;
- `bootstrap/app.php` and `/health/ready`; and
- domain-index navigation.

Existing architecture tests continue to enforce repository-wide Markdown links, naming/ownership, domain roots and P1–P3 documentation gates.

## 14. Protected candidate validation

Exact candidate/evidence head `66b2ca498ac89e550d3e718b174e07172e7409bd` passed:

- Dependency Review `31512996437` — success;
- CodeQL `31512996420` — success; and
- CI `31512996421` — success, including:
  - frontend quality/build — success;
  - PostgreSQL migrations — success;
  - Pint — **485 files**;
  - PHPStan/Larastan — **345/345, 0 errors**;
  - ParaTest/PHPUnit — **381 tests / 8,290 assertions**;
  - P4 interface/profile/focused-contract/reused-contract/route-inventory assertions — success;
  - repository-wide local Markdown-link validation — success;
  - immutable image build — success;
  - ephemeral staging — success;
  - backup/restore — success; and
  - image scan — success.

The candidate gate is therefore accepted with no P4 defect exposed.

## 15. Final transition gate

The remaining P4 requirement is purely the hard evidence-head rule: after this report, the coverage matrix, and the DCP ledger record P4 completion/P5 selection, that exact resulting branch head must independently pass protected Dependency Review, CodeQL, and complete CI.

Until that final-head result is green, **do not begin DCP-P5 implementation**.
