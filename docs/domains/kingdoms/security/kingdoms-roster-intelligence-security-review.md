# KINGDOMS-001 security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-001` whole-increment hardening / `K1-P6`  
**Status:** Accepted repository security evidence

This review closes the security boundary across the complete Kingdoms roster-intelligence increment. It supplements the slice reviews for the [Kingdom foundation](kingdoms-foundation-security-review.md), [roster](kingdoms-roster-security-review.md), [snapshots](kingdoms-snapshot-security-review.md), [intelligence](kingdoms-intelligence-security-review.md), and [CSV migration](kingdoms-csv-security-review.md).

## Assets and trust boundaries

Protected assets include:

- Alliance→Kingdom association state;
- neutral global `Kingdom` and `Player` references;
- alliance-owned roster state and membership links;
- private manager notes;
- append-only player snapshots and actor/import provenance;
- import previews, resolution decisions, checksums and commit summaries;
- derived roster intelligence;
- member and management exports;
- audit records and transactional-outbox evidence; and
- the distinction between internal durable Kingdoms events and public integration contracts.

The relevant trust boundaries are authenticated browser access, active-Alliance tenancy, privileged password-confirmed management, global neutral game identity, untrusted CSV input, local spreadsheet consumption of exports, the internal transactional outbox, and the existing external API/webhook subsystem.

## Cross-alliance isolation

A Kingdom and a `Player` are global neutral references. They are never authorization keys.

Alliance-owned roster entries, membership links, private notes, snapshots, imports, exports and derived metrics are always re-established from the active Alliance. Submitted roster, membership, import and ambiguity-resolution identifiers are resolved inside that tenant boundary. Two alliances may share the same Kingdom and neutral player identity without gaining access to each other's observations or management data.

Feature and acceptance tests cover same-Kingdom tenants and cross-alliance object-ID attempts for roster, history and imports.

## Authorization and privileged mutation

- Ordinary roster, history and aggregate-intelligence reads require `alliance.view`.
- Roster management, snapshot recording, import preview/confirmation, management export and manager comparison detail require `kingdoms.manage`.
- Built-in Owner, Leader and Officer roles receive `kingdoms.manage`; Member and specialist roles do not.
- Alliance→Kingdom association remains an alliance-setting operation under `alliance.manage`.
- Sensitive Kingdom association, roster, snapshot and import mutations require recent password confirmation at the route boundary.
- Platform-administrator status does not implicitly grant alliance roster-management authority.

Privileged state changes are audited and use the transactional outbox when a durable internal event is required.

## Identity ambiguity and integrity

Stable game-player ID inside a Kingdom is the only automatic roster identity-match key. Display names are not unique and are never sufficient for automatic merge.

Manual roster creation can intentionally create separate neutral identities with the same display name when no stable ID is known. CSV name matches are classified ambiguous even when there is only one candidate; a manager must explicitly choose a previewed same-alliance candidate or create a new identity.

This leaves a residual human-resolution risk, but the stored preview, resolution payload, audit evidence and append-only history preserve explainability and correction paths.

## Historical integrity, replay and idempotency

Player snapshots are append-oriented observations. Normal roster edits and departures do not destroy prior snapshots.

Exact accepted snapshot retries use a deterministic alliance-scoped idempotency key, while later capture times create new history. CSV imports are uniquely identified by Alliance + schema version + SHA-256 file checksum; committed re-upload and re-confirmation are no-ops. CSV confirmation is one database transaction and fails closed if previewed identity assumptions changed.

These controls prevent routine browser/worker retries from multiplying history or partially applying a migration batch.

## CSV and spreadsheet boundary

CSV upload is treated as untrusted text:

- UTF-8 only with NUL rejection;
- exact documented header schema;
- 1 MiB maximum file size;
- 500 nonblank data-row maximum;
- strict field validation, integer power and timestamp rules;
- duplicate stable IDs in one file rejected;
- complete dry-run validation before any roster/snapshot persistence; and
- spreadsheet formulas/macros are never executed during parsing.

Exported string cells are neutralized before CSV encoding when they begin with spreadsheet formula triggers (`=`, `+`, `-`, `@`, tab, carriage return or line feed). Ordinary member export omits manager-only fields; management export requires `kingdoms.manage`. Export responses are private/non-cacheable and use `nosniff`.

## Derived intelligence and abuse resistance

Roster intelligence is derived only from tenant-owned recorded snapshots. Missing observations remain missing rather than being converted to zero. Stale data is identified separately. Seven- and 30-day comparisons use bounded historical baselines and do not interpolate unsupported precision.

Manager comparison detail is alphabetical and diagnostic. The implementation does not rank players by growth, generate punitive scores, or convert power growth into Contribution-domain credit.

## Public API and webhook exposure

`KINGDOMS-001` does **not** approve a public roster/snapshot/intelligence API or an external Kingdoms webhook schema.

The read-only `/api/v1` contract remains limited to alliance, events and contributions. `K1-P6` closes an inherited generic-webhook fan-out hazard: all `kingdoms.*` outbox events are explicitly excluded from external webhook delivery, including wildcard subscriptions. The events remain durable internal outbox evidence and can be exposed later only through an explicitly approved integration-contract change.

This distinction is important because the generic webhook subsystem otherwise treats published tenant outbox events as eligible for wildcard subscriptions. Acceptance tests guard both the API route surface and the webhook exclusion.

## Operational and logging boundary

The increment adds no dedicated scheduler, worker queue, external crawler or game-data ingestion service. Kingdoms mutations use normal request/trace correlation, audit evidence and the existing transactional outbox. CSV imports persist preview/commit state and result summaries so rejected or failed batches can be diagnosed without logging raw private notes as operational telemetry.

Existing production infrastructure controls remain unchanged. A green `KINGDOMS-001` repository gate does not establish real-production ingress, egress, alerting, operator, key/media recovery or support evidence.

## Explicitly excluded risk surface

The accepted increment does not implement:

- scraping, OCR, bots or undocumented Kingshot APIs;
- automated external game-data ingestion;
- transfer planning or diplomacy/NAP management;
- cross-alliance roster intelligence or rankings;
- automated player scoring/recommendations;
- public Kingdoms API scopes/endpoints; or
- external Kingdoms webhook event schemas.

Those capabilities require separate product/security review before implementation.

## Acceptance verification

The `K1-P6` protected implementation head `7f743507b70865692290f517cd2de494ec54abae` passed the whole-workflow, authorization/tenant, identity/idempotency, CSV/export, internal-webhook, rollback, realistic-volume query, accessibility, Dependency Review, CodeQL, staging/recovery and image-scan gates recorded in the [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md).

This is accepted repository/product security evidence. It does not approve a real production cutover or substitute for external production infrastructure/operator controls.
