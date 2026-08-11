# Operations, reliability, and recovery coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P3` — Operations, reliability, and recovery completeness  
**Inventory state:** Validated candidate — complete transition prepared for final evidence-head validation

## 1. Purpose

This is the authoritative DCP-P3 inventory. It records the required living domain operations profiles, focused operational runbooks, existing accepted domain operational evidence, and the shared runtime/runbook contracts used to operate and recover the implemented repository.

The frozen P3 content inventory is fully implemented and its corrected candidate evidence head passed protected validation. The phase transition is recorded in the final exit/status evidence chain and becomes authoritative only when that exact final branch head also passes the repository's protected checks.

## 2. Shared runtime baseline

The following current shared living documents remain authoritative and are consumed by every domain profile where applicable:

- `docs/operations/background-processing.md`
- `docs/operations/configuration-reference.md`
- `docs/operations/observability.md`
- `docs/operations/runbooks/deployment.md`
- `docs/operations/runbooks/rollback.md`
- `docs/operations/runbooks/backup-restore.md`
- `docs/operations/runbooks/incident-response.md`
- `docs/operations/production-launch-runbook.md`
- `docs/operations/release-checklist.md`

Historical Phase 1–6 operations, migration/rollback, maintenance and disaster-recovery records remain evidence rather than current domain authority.

## 3. Frozen domain inventory

| Domain | Primary operational concerns | P3 decision | Required focused living runbooks | Existing focused evidence | Status |
| --- | --- | --- | --- | --- | --- |
| Alliances | Alliance lifecycle/context, request-time tenant resolution, session/Redis dependency, tenant snapshot propagation | Profile only | None | Phase 1 operations/migration evidence | Validated |
| Audit | append-oriented audit evidence, attribution/correlation, retention/redaction coordination | Profile only | None | Phase 1/6 evidence | Validated |
| Authorization | tenant RBAC state, hierarchy/last-Owner integrity, synchronous assignment/removal | Profile only | None | Phase 1 evidence | Validated |
| Content | scheduled publishing, private media/object storage, scanner/storage degradation, branding eligibility | Focused runbook | `scheduled-publishing-and-media.md` | Phase 2 operations/migration evidence | Validated |
| Contributions | contribution/report state, Event reconciliation, scheduled report source data/provenance | Profile only | None | Phase 5 operations/migration evidence | Validated |
| Events | recurrence, occurrences, registration/waitlist/attendance concurrency, reminder source state | Profile only | None | Phase 3 operations evidence | Validated |
| Identity | account/MFA/recovery/session state, APP_KEY and Redis/session dependency, verification/auth failure behavior | Profile only | None | Phase 1/6 evidence | Validated |
| Integrations | read API credentials plus durable webhook fan-out, Redis/Horizon, DNS/HTTP/egress degradation and retries | Focused runbook | `webhook-delivery.md` | Phase 6 operations evidence | Validated |
| Kingdoms | roster/snapshot/import intelligence, transfer planning, Alliance intelligence/diplomacy | Existing review set | Existing 3 accepted operations guides | K1–K3 domain operations guides | Validated |
| Memberships | membership/invitation lifecycle, TTL, email delivery dependency, transactional acceptance/reactivation | Profile only | None | Phase 1 evidence | Validated |
| Notifications | recurring reminder/report materialization/queueing, deterministic delivery/run identities, outbox handoff | Focused runbook | `scheduled-delivery.md` | Phase 3/5/6 evidence | Validated |
| Platform | transactional outbox, config/launch checks, usage snapshots, account deletion, retention/legal hold/export orchestration | Focused runbooks | `transactional-outbox.md`, `lifecycle-retention.md` | Phase 6 operations/DR/database maintenance evidence | Validated |
| Rallies | Alliance-private coordination state, assignment/participation integrity, synchronous requests | Profile only | None | Phase 3 operations evidence | Validated |
| Recruitment | public/private intake persistence plus scheduled expired-candidate anonymization and conversion follow-on | Focused runbook | `retention-and-anonymization.md` | Phase 4 operations/migration evidence | Validated |

## 4. Mandatory profile result

P3 requires exactly one current living operations profile for each canonical domain:

```text
docs/domains/<domain>/operations/README.md
```

All 14 profiles follow `operations-documentation-standard.md`, link to the owning domain plus shared operations index, and document runtime shape, persistent state, configuration/dependencies, background processing, diagnostics, failure/recovery/replay, backup/rollback, capacity, degradation, stop conditions and evidence.

## 5. Focused-runbook rationale

### Content — scheduled publishing and media

Content combines recurring due-publication with private file/object storage, upload screening and public branding eligibility. Scheduler failure, scanner/storage degradation and database/media recovery-set consistency require a capability-specific operator procedure.

### Integrations — webhook delivery

Webhook delivery spans transactional outbox fan-out, durable delivery rows, Redis/Horizon, HTTP/DNS/egress, signing, bounded retries and permanently failed state. It is the repository's primary queued external-delivery workload and requires dedicated diagnosis/recovery guidance.

### Notifications — scheduled delivery

Event reminder and contribution-report coordination are recurring scheduler workflows with deterministic materialization/outbox identities. Operators need one guide explaining source-state diagnosis, safe catch-up and how to avoid duplicate logical delivery.

### Platform — transactional outbox

The outbox is a shared durable handoff used across domains. At-least-once publication, claim/retry/error state and consumer failure can block downstream work while the originating transaction remains committed.

### Platform — lifecycle and retention

Account deletion, usage capture, retention enforcement, legal holds and destructive/anonymizing operations have recurring scheduler behavior and explicit fail-closed stop conditions. They must not be recovered through direct row edits or hold bypass.

### Recruitment — retention and anonymization

Recruitment has daily scheduled anonymization of eligible unsuccessful candidates and privacy-sensitive persistent data. Eligibility/retention state must be rechecked under the supported workflow and evidence preserved without retaining unnecessary applicant content.

## 6. Profile-only rationale

Alliances, Audit, Authorization, Contributions, Events, Identity, Memberships and Rallies have meaningful persisted operational state but no independently complex queue/destructive/external-recovery lifecycle beyond what is executable in one domain profile plus shared runbooks.

Contributions and Events participate in scheduled workflows, but the recurring coordination/recovery machinery is owned operationally by Notifications; their profiles document source-state/provenance and point to the Notifications runbook rather than duplicating authority.

## 7. Existing Kingdoms operations set

Kingdoms already has accepted domain-owned operations guides for roster intelligence, transfer planning, and Alliance intelligence.

P3 normalized `docs/domains/kingdoms/operations/README.md` into the mandatory current domain profile while retaining and indexing those three accepted guides. Their accepted format is preserved rather than cosmetically rewritten into the new focused-runbook template.

## 8. Shared versus domain authority

Shared `docs/operations/` owns runtime topology, config validation, health/observability, deployment, backup/restore, rollback, incident response and cross-domain scheduler/queue/outbox mechanics.

Domain profiles/runbooks own domain state semantics, domain-specific healthy progression, failure diagnosis, replay/reconciliation safety, recovery verification, performance/query boundaries and prohibited operator shortcuts.

The shared and domain indexes expose this split directly and link all 14 domain profiles.

## 9. Recovery/rollback reference completeness

Every domain profile states:

- whether its state is fully PostgreSQL-backed or also depends on private object storage/secrets/external recipient state;
- whether normal recovery is safe rerun, durable replay, reconciliation, image rollback, database restore or operator escalation;
- applicable schema/data rollback cautions;
- representative domain state to verify after restore/rollback; and
- prohibited actions that would fabricate success, destroy evidence or violate authorization/privacy/legal-hold constraints.

The focused runbooks make the executable catch-up/replay/retention paths explicit for the high-complexity P3 boundaries.

## 10. P3 CI enforcement

`tests/Architecture/OperationsDocumentationTest.php` enforces:

- exactly 14 canonical code domains and one living operations profile for each;
- required profile metadata and 12-section order;
- owning-domain and shared-operations links;
- the exact frozen six-runbook P3 inventory;
- focused-runbook indexing, metadata and 10-section order;
- retention/indexing of the three accepted Kingdoms operations guides;
- no migration of the new domain-specific runbooks into top-level `docs/operations/`; and
- shared operations navigation to the standard, matrix and every domain profile.

The existing repository-wide local Markdown-link test remains the link-integrity gate.

## 11. Candidate validation evidence

The initial P3 evidence head `9f03f918daa16d63cfbac538b57755289677d35d` exposed one lint-only defect in the new architecture test: four unused imports. Dependency Review and CodeQL passed; CI stopped at Pint before P3 semantic assertions.

After removing only those unused imports, corrected evidence head `a67f93706eff4285a229df1f6ce057f2be3b5adc` passed:

- Dependency Review `31508211709` — success;
- CodeQL `31508211738` — success; and
- CI `31508211931` — success, including frontend quality/build, PostgreSQL migrations, Pint 484 files, PHPStan/Larastan 345/345 with 0 errors, 375 tests / 7,628 assertions, the P3 architecture and repository Markdown-link gates, immutable image build, ephemeral staging, backup/restore, and image scan.

## 12. P3 exit checklist

- [x] Operations documentation standard adopted.
- [x] Repository-wide operations/reliability/recovery inventory frozen.
- [x] 14/14 living domain operations profiles implemented.
- [x] 6/6 new focused living operations runbooks implemented.
- [x] Kingdoms profile normalized and existing 3 accepted guides retained/indexed.
- [x] Shared operations index and domain navigation complete/non-conflicting.
- [x] Recovery/rollback references complete across all profiles/runbooks.
- [x] P3 structural/metadata/heading/frozen-inventory CI enforcement active.
- [x] Local Markdown-link validation passes with all new operational links.
- [x] Protected candidate validation passes on the exact corrected P3 evidence head.
- [x] Final P3 exit/status transition is prepared as one frozen branch head for protected validation.

The transition to DCP-P4 is authoritative only after the exact branch head containing the final P3 exit/status records passes protected Dependency Review, CodeQL, and complete CI. This external exact-head check avoids an impossible self-referential commit-hash cycle and requires no subsequent branch mutation after success.
