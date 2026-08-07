# Phase 2 Exit Report

**Phase:** Content and Public Presence  
**Status:** Accepted  
**Branch:** `agent/phase-2-content-public-presence`

## Objective

Give each alliance a usable public identity and a controlled internal information hub while preserving the Phase 1 tenant and authorization boundary.

## Delivered scope

- Public alliance profile with name, kingdom, language, time zone, description, recruitment status, color, logo, and banner branding.
- Draft/published announcements, guides, rules, event instructions, and reference pages.
- Public versus members-only visibility with separate public/member/manager query paths.
- Content categories, ordering, locale fields, bounded search/filtering, revision history, scheduled publication, archival, and safe draft restoration.
- Alliance home notices plus an explicit Phase 3-owned upcoming-activities slot without introducing event-domain tables early.
- Private tenant-prefixed media upload with MIME/size enforcement, scanner contract, baseline signature screening, SHA-256 checksums, branding attachment controls, and archive lifecycle.
- Content/profile/media audit records and transactional outbox events using the Phase 1 at-least-once publisher.
- Manager-facing content console and `docs/CONTENT_MANAGEMENT.md` so authorized alliance leadership can operate the public/content surface without developer assistance.

## Security and tenant-boundary evidence

Phase 2 preserves the fail-closed Phase 1 active-alliance boundary and adds database-enforced same-alliance references for content/category/revision/media relationships.

Automated/adversarial coverage verifies:

- public direct URLs and search cannot expose drafts, scheduled-but-unpublished items, archived items, member-only content, or revision history
- active members can read published member-visible content only in their active alliance
- content management requires `content.manage`; mutations additionally require verified identity and recent password confirmation
- cross-alliance category and branding-media references are rejected
- editing published content creates a new revision and returns the item to draft rather than silently changing live content
- restoring historical content creates a new draft revision and never republishes implicitly
- due scheduled publication is row-locked and idempotency-aware through the outbox boundary
- uploaded media is tenant-prefixed, private, MIME/size checked in both HTTP/service layers, security-screened before persistence, and removed if persistence fails
- public branding streams only clean, active image assets attached to the requested alliance
- authored body text is rendered as escaped plain text; Phase 2 pages prohibit `v-html`

The Phase 2 security analysis is recorded in `docs/PHASE_2_THREAT_MODEL.md`. No unresolved critical or high application-security finding is accepted for this phase.

## Accessibility evidence

`docs/PHASE_2_ACCESSIBILITY.md` records WCAG 2.2 AA structural/interaction expectations for Phase 2.

`ContentAccessibilityGuardTest` protects the public/member/management Vue surfaces against removal of their `main` landmark, raw `v-html`, positive tabindex values, and native buttons without an explicit type. The UI uses semantic links/forms/buttons, labeled controls, visible text for state, responsive layout, and local-time rendering from absolute timestamps.

Actual production branding contrast, keyboard/reflow, and assistive-technology smoke checks remain release-readiness activities because real alliance branding is deployment/content-specific.

## Migration and recovery evidence

`2026_08_07_010000_create_content_domain_tables.php` is additive to the accepted Phase 1 schema and uses dependency-safe rollback ordering.

`ContentMigrationRollbackTest` proves the Phase 2 migration `down()` and `up()` paths on the CI PostgreSQL 18 path by removing and recreating all six Phase 2 tables in an isolated test transaction. Operational behavior is documented in `docs/PHASE_2_MIGRATION_ROLLBACK.md`.

Database backup/restore remains the existing verified PostgreSQL process. Phase 2 explicitly does **not** claim that `bin/backup` contains uploaded binaries. External staging is configured for S3 media, and production startup fails closed unless `CONTENT_MEDIA_DISK=s3` with a configured bucket. Object-store durability, versioning/retention/recovery, and approved malware-scanner binding are deployment-readiness controls documented in `docs/PHASE_2_OPERATIONS.md`.

## Observability and operations

- Scheduled publication runs through the existing scheduler and rechecks due state under row locks.
- Content/profile/media mutations retain alliance/actor audit attribution and request/trace correlation where an HTTP request exists.
- The transactional outbox remains at-least-once with retry/idempotency semantics.
- No new public dependency-detail health endpoint is introduced; existing liveness/readiness remains unchanged.
- Operations guidance defines monitoring/alert implications for scheduler availability, overdue scheduled content, outbox lag, media screening/storage failure, and object-store recovery.

## Verified acceptance evidence

Implementation/audit head `3c137d74a608e57605256cd9e58b5a6cbee62a36` passed:

- PostgreSQL migrations
- Pint: 159 PHP files
- PHPStan: zero errors
- PHPUnit/ParaTest: **98 tests / 570 assertions**
- frontend ESLint, Prettier, Vue/TypeScript checking, and production Vite build
- Composer and npm vulnerability audits
- Dependency Review workflow `31155725904`
- CodeQL workflow `31155726592`
- CI workflow `31155726752`
- immutable production image build
- ephemeral staging deployment across app/web/worker/scheduler roles
- destructive database backup/restore with release/image provenance
- Trivy HIGH/CRITICAL image scan

Acceptance-report head `1f73da358c1e1507c2c070b22224d067e118033a` independently repeated all required workflows successfully:

- Dependency Review workflow `31156084812`: success
- CodeQL workflow `31156084422`: success
- CI workflow `31156085482`: PHP, frontend, immutable image, staging, destructive recovery, and Trivy all success
- no unresolved pull-request review threads

## Exit criteria

- [x] An authorized content manager can operate the alliance public page without developer assistance.
- [x] Public users cannot discover member-only content or unpublished revisions.
- [x] Content changes are versioned and recoverable through immutable revisions and draft restoration.
- [x] Authorization and tenant-isolation tests cover Phase 2 routes, queries, revisions, and storage references.
- [x] New scheduled/outbox/storage boundaries are tenant-aware and documented.
- [x] Security review identifies no unresolved critical or high application-security finding.
- [x] Accessibility implementation and automated source guards meet the agreed Phase 2 standard.
- [x] Phase 2 migration forward/rollback behavior is tested and documented.
- [x] Logging, trace/audit, health, scheduler, storage, recovery, metrics, and alert implications are documented.
- [x] User and technical documentation are updated.
- [x] Staging deployment, database recovery, and vulnerability scanning pass.

## Acceptance decision

**Phase 2 — Content and Public Presence: ACCEPTED.**

PR #12 may be merged once repository-required checks remain green on this documentation-only final head. Phase 3 must not start until the Phase 2 pull request is merged into `main`.
