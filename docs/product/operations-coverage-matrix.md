# Operations, reliability, and recovery coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P3` — Operations, reliability, and recovery completeness  
**Inventory state:** Frozen — implementation in progress

## 1. Purpose

This is the authoritative DCP-P3 inventory. It records the required living domain operations profiles, focused operational runbooks, existing accepted domain operational evidence, and the shared runtime/runbook contracts used to operate and recover the implemented repository.

P3 is complete only when every required profile/runbook in this matrix exists, shared/domain authority is consistent, P3 CI enforcement is active, and protected candidate/final-head validation passes.

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
| Alliances | Alliance lifecycle/context, request-time tenant resolution, session/Redis dependency, tenant snapshot propagation | Profile only | None | Phase 1 operations/migration evidence | In progress |
| Audit | append-oriented audit evidence, attribution/correlation, retention/redaction coordination | Profile only | None | Phase 1/6 evidence | In progress |
| Authorization | tenant RBAC state, hierarchy/last-Owner integrity, synchronous assignment/removal | Profile only | None | Phase 1 evidence | In progress |
| Content | scheduled publishing, private media/object storage, scanner/storage degradation, branding eligibility | Focused runbook | `scheduled-publishing-and-media.md` | Phase 2 operations/migration evidence | In progress |
| Contributions | contribution/report state, Event reconciliation, scheduled report source data/provenance | Profile only | None | Phase 5 operations/migration evidence | In progress |
| Events | recurrence, occurrences, registration/waitlist/attendance concurrency, reminder source state | Profile only | None | Phase 3 operations evidence | In progress |
| Identity | account/MFA/recovery/session state, APP_KEY and Redis/session dependency, verification/auth failure behavior | Profile only | None | Phase 1/6 evidence | In progress |
| Integrations | read API credentials plus durable webhook fan-out, Redis/Horizon, DNS/HTTP/egress degradation and retries | Focused runbook | `webhook-delivery.md` | Phase 6 operations evidence | In progress |
| Kingdoms | roster/snapshot/import intelligence, transfer planning, Alliance intelligence/diplomacy | Existing review set | Existing 3 accepted operations guides | K1–K3 domain operations guides | In progress |
| Memberships | membership/invitation lifecycle, TTL, email delivery dependency, transactional acceptance/reactivation | Profile only | None | Phase 1 evidence | In progress |
| Notifications | recurring reminder/report materialization/queueing, deterministic delivery/run identities, outbox handoff | Focused runbook | `scheduled-delivery.md` | Phase 3/5/6 evidence | In progress |
| Platform | transactional outbox, config/launch checks, usage snapshots, account deletion, retention/legal hold/export orchestration | Focused runbooks | `transactional-outbox.md`, `lifecycle-retention.md` | Phase 6 operations/DR/database maintenance evidence | In progress |
| Rallies | Alliance-private coordination state, assignment/participation integrity, synchronous requests | Profile only | None | Phase 3 operations evidence | In progress |
| Recruitment | public/private intake persistence plus scheduled expired-candidate anonymization and conversion follow-on | Focused runbook | `retention-and-anonymization.md` | Phase 4 operations/migration evidence | In progress |

## 4. Mandatory profile result

P3 requires exactly one current living operations profile for each canonical domain:

```text
docs/domains/<domain>/operations/README.md
```

All 14 profiles follow `operations-documentation-standard.md` and link to the owning domain plus shared operations index.

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

Alliances, Audit, Authorization, Contributions, Events, Identity, Memberships and Rallies have meaningful persisted operational state but no independently complex queue/destructive/external-recovery lifecycle beyond what can be made executable in one domain profile plus shared runbooks.

Contributions and Events participate in scheduled workflows, but the recurring coordination/recovery machinery is owned operationally by Notifications; their profiles document source-state/provenance and point to the Notifications runbook rather than duplicating authority.

## 7. Existing Kingdoms operations set

Kingdoms already has accepted domain-owned operations guides for:

- roster intelligence;
- transfer planning; and
- Alliance intelligence.

P3 keeps those accepted guides and normalizes `docs/domains/kingdoms/operations/README.md` into the mandatory current domain profile while retaining the three guides as indexed focused evidence. Their historical accepted format is not rewritten merely to match the new focused-runbook template.

## 8. Shared versus domain authority

Shared `docs/operations/` owns runtime topology, config validation, health/observability, deployment, backup/restore, rollback, incident response and cross-domain scheduler/queue/outbox mechanics.

Domain profiles/runbooks own domain state semantics, domain-specific healthy progression, failure diagnosis, replay/reconciliation safety, recovery verification, performance/query boundaries and prohibited operator shortcuts.

When a domain guide needs backup/restore or deployment, it links to the shared runbook and adds only domain-specific verification.

## 9. Recovery/rollback reference completeness

Every domain profile must state:

- whether its state is fully PostgreSQL-backed or also depends on private object storage/secrets/external recipient state;
- whether normal recovery is safe rerun, durable replay, reconciliation, image rollback, database restore or operator escalation;
- whether schema reversal is supported only in test/development versus production-safe;
- what representative domain state must be verified after restore/rollback; and
- what actions are prohibited because they would fabricate success, destroy evidence or violate authorization/privacy/legal-hold constraints.

## 10. P3 exit checklist

- [x] Operations documentation standard adopted.
- [x] Repository-wide operations/reliability/recovery inventory frozen.
- [ ] 14/14 living domain operations profiles implemented.
- [ ] 6/6 new focused living operations runbooks implemented.
- [ ] Kingdoms profile normalized and existing 3 accepted guides retained/indexed.
- [ ] Shared operations index and domain navigation complete/non-conflicting.
- [ ] Recovery/rollback references complete across all profiles/runbooks.
- [ ] P3 structural/metadata/heading/frozen-inventory CI enforcement active.
- [ ] Local Markdown-link validation passes with all new operational links.
- [ ] Protected candidate validation passes on the exact P3 evidence head.
- [ ] P3 exit/status evidence finalized and final head protected-green.

Until every item is checked, the correct `continue` decision is **finish DCP-P3**.
