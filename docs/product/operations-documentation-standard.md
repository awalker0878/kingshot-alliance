# Operations documentation standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program documentation standard  
**Status:** Current  
**Primary phase:** `DCP-P3` — Operations, reliability, and recovery completeness

## 1. Purpose

This standard defines how the repository documents current operating behavior, failure diagnosis, recovery, replay/reconciliation, migration/rollback, capacity assumptions, and operator evidence without turning historical phase records into living runbooks.

The standard applies to all canonical code domains and to shared runtime/runbook material under `docs/operations/`.

## 2. Source-of-truth precedence

When operational sources disagree, use this order:

1. executable runtime behavior and configuration (`routes/console.php`, `config/*.php`, deployment/backup/restore scripts, migrations and domain actions/jobs);
2. current shared living operations contracts under `docs/operations/`;
3. current domain operations profiles and focused runbooks under `docs/domains/<domain>/operations/`;
4. current domain/capability contracts and security profiles; then
5. historical phase/increment operations, migration, exercise and exit evidence.

Historical records explain what was accepted at a point in time. They do not override current runtime behavior.

## 3. Ownership model

Shared runtime concerns belong under `docs/operations/`, including:

- deployment/release and immutable-image mechanics;
- hosted configuration validation;
- PostgreSQL/Redis/runtime topology;
- scheduler/Horizon/outbox operating model;
- cross-domain observability and health signals;
- backup/restore, rollback and incident-response runbooks; and
- production launch/release controls.

Domain-specific operating semantics belong under:

```text
docs/domains/<domain>/operations/
  README.md
  <focused-runbook>.md
```

A domain runbook consumes shared runtime procedures rather than redefining deployment, backup, health, or incident management.

## 4. Mandatory domain operations profile

Every canonical code domain must have:

`docs/domains/<domain>/operations/README.md`

This deterministic rule is intentionally broader than the P3 minimum phrase “stateful/async/integration-heavy”: every current canonical domain owns persistent state or stateful behavior, and all therefore require an operator-discoverable failure/recovery boundary.

Required metadata:

- `**Document type:** Living domain operations profile`
- `**Status:** Current`
- `**Owning domain:** <Domain>`
- `**Code owner:** app/Domain/<Domain>`
- `**Primary operational boundary:** ...`

Required ordered sections:

1. `## 1. Operational purpose and runtime shape`
2. `## 2. Persistent state and ownership`
3. `## 3. Configuration and runtime dependencies`
4. `## 4. Normal flow and background processing`
5. `## 5. Health, observability and diagnostics`
6. `## 6. Failure modes and diagnosis`
7. `## 7. Recovery, replay and reconciliation`
8. `## 8. Backup, restore, migration and rollback`
9. `## 9. Capacity, query and performance boundaries`
10. `## 10. External-service degradation`
11. `## 11. Safe operator actions and stop conditions`
12. `## 12. Evidence, focused runbooks and related documentation`

A section may state that a concern is not applicable, but it must explain why from current behavior rather than omit the concern.

## 5. Focused operational runbook threshold

Create a focused living runbook when a capability has an independently complex operating lifecycle that cannot be made executable from the domain profile alone, especially when it has one or more of:

- recurring scheduler work or queue workers with durable business state;
- at-least-once delivery/replay/reconciliation semantics;
- external dependency degradation requiring capability-specific diagnosis;
- destructive retention/anonymization/lifecycle operations;
- private object/file storage with separate recovery dependencies;
- explicit catch-up commands or backlog draining behavior; or
- capability-specific performance/query acceptance boundaries important to safe recovery.

Do not create one file per command/controller/model. Coherent capability boundaries should share one runbook.

## 6. Focused runbook format

Required metadata:

- `**Document type:** Living capability operations runbook`
- `**Status:** Current`
- `**Owning domain:** <Domain>`
- `**Capability:** ...`
- `**Code owner:** app/Domain/<Domain>`

Required ordered sections:

1. `## 1. Scope, prerequisites and safety boundary`
2. `## 2. Runtime and persistent state`
3. `## 3. Healthy operating flow`
4. `## 4. Signals and diagnostics`
5. `## 5. Failure modes and triage`
6. `## 6. Recovery, replay and reconciliation`
7. `## 7. Capacity and dependency degradation`
8. `## 8. Backup, migration and rollback`
9. `## 9. Stop conditions and prohibited operator actions`
10. `## 10. Validation and evidence to retain`

## 7. Recovery and replay rules

Living operations documentation must distinguish:

- retrying a safe idempotent command;
- replaying durable asynchronous work;
- reconciling derived/materialized state from its authoritative source;
- restoring a database/media recovery set;
- rolling back an application image; and
- reversing a database migration.

A restart is never sufficient proof of recovery. Operators must verify the durable state that was expected to advance.

Never document direct SQL mutation, deleting audit/outbox evidence, bypassing legal holds, weakening tenant/authorization rules, or fabricating successful state as a normal recovery procedure.

## 8. Backup, restore and rollback boundary

Domain profiles must identify which owned state is covered by PostgreSQL backup and which state depends on external/private storage or secrets.

The shared backup/restore and rollback runbooks remain authoritative for repository-supported mechanics. Domain documentation adds only domain-specific verification and data-dependency semantics.

Application rollback does not imply database rollback. Prefer backward-compatible migrations and forward fixes. Destructive schema reversal or data restore requires explicit operator approval and recovery evidence.

## 9. Observability and diagnostics

Documentation must name implemented signals rather than aspirational dashboards. Prefer:

- request/trace IDs;
- named durable row state/status/attempt timestamps;
- scheduler/Horizon process state and queue ownership;
- outbox backlog/error fields;
- readiness/liveness;
- audit correlation;
- immutable release/image identity; and
- domain-specific counts/ages/checksums/provenance.

Do not claim production alerts, telemetry exporters, provider dashboards, on-call ownership or production retention exist merely because repository signals are available.

## 10. Capacity and performance

Repository query/performance fixtures are regression/N+1 gates unless explicitly designed and accepted as capacity tests. Domain docs must not convert fixture sizes into production capacity claims.

Backlog catch-up limits may be raised only within implemented bounds and with database/queue/dependency capacity assessed. A larger batch is not the default recovery action.

## 11. External services and configuration

Profiles must identify applicable runtime dependencies such as PostgreSQL, Redis, S3-compatible private storage, SMTP, malware scanning, webhook HTTP/DNS/egress, and encryption keys.

Repository CI can validate configuration shape and staging behavior; it cannot prove production network policy, provider durability, secret custody, real mail deliverability, external endpoint behavior, or production capacity.

## 12. Evidence retention

Where applicable retain immutable or stable evidence such as:

- release SHA/image digest;
- request/trace/incident/change identifiers;
- command/run timestamp and bounded parameters;
- affected tenant/domain record IDs and before/after status counts;
- backup filename/checksum/manifest ID;
- migration result;
- queue/outbox backlog and failure counts;
- recovery validation results; and
- reviewer/approval identity in the approved operational system.

Do not retain raw credentials, recovery secrets, unrestricted private payloads, or unnecessary applicant/member/private notes in operational evidence.

## 13. Historical evidence

Existing Phase 1–6 operations, migration/rollback and disaster-recovery records remain historical evidence. Existing Kingdoms K1–K3 domain operations guides remain accepted domain evidence and may be normalized only when needed to satisfy current P3 ownership/navigation, not cosmetically rewritten.

## 14. P3 validation rules

High-signal CI should enforce at least:

- exact parity between 14 code domains and 14 living domain operations profiles;
- required profile metadata and 12-section order;
- links from profiles to the owning domain and shared operations index;
- exact focused-runbook inventory frozen by the P3 coverage matrix;
- focused-runbook metadata, indexing and 10-section order for new P3 living runbooks;
- domain-specific living runbooks remain under their owning domain rather than top-level `docs/operations/`; and
- local Markdown links remain valid.

Do not force historical accepted runbooks into the new format solely for cosmetics; their existence/indexing is enforced according to the frozen P3 inventory.
