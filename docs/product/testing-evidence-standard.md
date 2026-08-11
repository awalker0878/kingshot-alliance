# Testing, evidence, and traceability documentation standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Primary phase:** `DCP-P5` — Testing, evidence, and traceability completeness

## 1. Purpose

This standard defines how the repository documents **what proves current behavior** and **what immutable evidence proves an accepted historical gate**.

P5 does not require one document per test file. It requires a deterministic validation map from critical domain claims to executable evidence classes, plus sufficient immutable identity for accepted phase/increment evidence so a future reviewer can distinguish:

- current living contract;
- current executable regression evidence;
- historical accepted evidence tied to a specific source revision/check run; and
- superseded or non-authoritative evidence.

## 2. Evidence classes

P5 recognizes five evidence classes.

### 2.1 Living contract

Present-tense domain/security/operations/interface documentation describes **what the current repository claims**. It is not historical acceptance evidence.

### 2.2 Living validation map

`docs/domains/<domain>/testing/README.md` explains **how current critical claims are tested**: applicable test suites, architecture/tenant/security/interface/concurrency/migration/performance/accessibility classes, and known gaps/non-capabilities.

It points to executable suite boundaries rather than duplicating every test method.

### 2.3 Executable validation evidence

Current executable evidence includes:

- PHPUnit suites configured by `phpunit.xml`;
- backend `composer check`;
- frontend `npm run check`;
- GitHub Actions Dependency Review;
- CodeQL;
- CI PostgreSQL migration execution;
- immutable production-image build;
- ephemeral staging deployment;
- backup/restore demonstration; and
- image vulnerability scanning.

Executable evidence proves the checked revision only.

### 2.4 Immutable acceptance evidence

Accepted phase/increment/DCP exit records preserve the immutable identity of the validated revision and protected checks. They are historical evidence, not living runtime contracts.

### 2.5 Operational/external evidence

Real production/deployment/operations evidence may exist outside repository CI. Repository documentation must record only evidence actually observed/approved; it must not infer production readiness from repository-green checks.

## 3. Source-of-truth precedence

For current behavior:

1. executable code/configuration;
2. executable tests;
3. current living domain/security/operations/interface/testing documentation;
4. current shared product/security/operations governance; and
5. historical accepted evidence.

For the historical question "what was accepted on this gate?", the accepted exit report and its recorded SHA/check identities are authoritative for that historical decision.

Historical evidence never overrides later current code.

## 4. Canonical domain testing profile

Every canonical code domain has exactly one living testing/evidence profile:

```text
app/Domain/<Domain>/
docs/domains/<domain>/testing/README.md
```

The profile maps critical claims to evidence classes. It does not create a parallel test specification and does not copy test source.

Required metadata:

```markdown
**Document type:** Living domain testing and evidence profile
**Status:** Current
**Owning domain:** <CanonicalDomain>
**Code owner:** `app/Domain/<CanonicalDomain>`
**Primary validation boundary:** <concise statement>
**P5 evidence decision:** <living suite map / historical evidence reused / no dedicated UI evidence / etc.>
```

Required section order:

1. `## 1. Critical claims and validation ownership`
2. `## 2. Executable suite mapping`
3. `## 3. Architecture and domain-boundary validation`
4. `## 4. Authorization, tenancy, security and privacy validation`
5. `## 5. Feature, interface and integration validation`
6. `## 6. Idempotency, concurrency and asynchronous validation`
7. `## 7. Persistence, migration, rollback and recovery evidence`
8. `## 8. Performance, query and capacity evidence`
9. `## 9. Accessibility and frontend evidence`
10. `## 10. Historical accepted evidence`
11. `## 11. Evidence identity, retention and supersession`
12. `## 12. Gaps, non-capabilities and related documentation`

Every section must be substantive or explicitly explain why a class is not applicable.

## 5. Executable PHPUnit suite taxonomy

`phpunit.xml` defines these canonical suites:

- `Architecture` → `tests/Architecture`;
- `Feature` → `tests/Feature`;
- `Integration` → `tests/Integration`;
- `Performance` → `tests/Performance`;
- `TenantIsolation` → `tests/TenantIsolation`; and
- `Unit` → `tests/Unit`.

A domain profile identifies which classes are material for its critical claims. It does not claim every domain has one physical test file in every suite.

### Architecture

Repository/domain ownership, dependency direction, forbidden surfaces, documentation structure, static accessibility guards, and other structural invariants.

### Feature

First-party HTTP/application behavior, authorization, payload/disclosure, user workflow, and domain action outcomes.

### Integration

Database-backed workflows, cross-domain collaboration, queue/outbox/integration behavior, migration/recovery behavior where organized as integration coverage.

### Performance

Explicit query-count, realistic-volume, bounded-work, capacity or regression gates where the domain has material performance acceptance criteria.

### TenantIsolation

Cross-Alliance object substitution, active-context, same-tenant reference and disclosure/mutation denial.

### Unit

Pure value objects, enum/state machines, parsers/serializers, recurrence/calculation/formatting and other deterministic logic that does not require full application orchestration.

## 6. Backend quality gate

`composer check` is the canonical backend quality command and currently runs:

1. Pint in test mode;
2. PHPStan/Larastan analysis; and
3. ParaTest over the configured PHPUnit suites.

A green domain profile is not independently accepted because one targeted test passed; protected phase/increment gates use the complete required backend command plus the applicable protected workflows.

## 7. Frontend quality and accessibility evidence

`npm run check` is the canonical frontend repository quality command and currently runs:

- ESLint check;
- Prettier check;
- Vue/TypeScript type checking; and
- production Vite build.

Frontend quality is **not** automatically accessibility conformance. Accessibility evidence is a combination of:

- domain/phase source-level accessibility guards where implemented;
- accepted accessibility review records;
- feature-level semantic/payload/permission coverage where applicable; and
- explicitly external/deployment-specific browser/device/screen-reader/branding validation when the acceptance record says it remains external.

P5 must not manufacture an automated browser accessibility suite that does not exist.

## 8. Security, tenancy and privacy traceability

Every domain testing profile maps its security-sensitive critical claims to the applicable evidence classes and its living security profile/reviews.

At minimum, when applicable, profiles identify coverage for:

- authentication/assurance;
- authorization/permission/hierarchy;
- active Alliance context;
- cross-tenant object substitution;
- public/member/manager disclosure separation;
- secrets/tokens/signatures;
- destructive/retention operations; and
- explicit prohibited external/automation boundaries.

A security document is not a substitute for an executable regression when the invariant can be tested in repository code.

## 9. Interface and integration traceability

For P4 material boundaries, the owning P5 profile identifies validation for:

- public/member/manager/admin route classes;
- request validation and disclosure payloads;
- machine API authentication/scopes/rate/row bounds;
- webhook eligibility/signatures/retry/idempotency;
- file/import/export schema/version/safety;
- command/job/scheduler behavior; and
- producer/outbox/internal-consumer/external-delivery separation.

The P4 interface profile remains the contract owner. The P5 profile only maps that contract to evidence.

## 10. Idempotency, concurrency and asynchronous evidence

Material idempotency/concurrency/asynchronous claims must be traceable to executable evidence when applicable, including:

- row-lock/capacity races;
- deterministic retry identity;
- append-oriented correction/replacement;
- duplicate-safe command execution;
- at-least-once outbox consumers;
- queue retry/backoff/terminal state; and
- restore/catch-up reconciliation.

Do not describe a workflow as idempotent merely because retry seems harmless; the owning contract/tests must define the identity and repeated-result semantics.

## 11. Migration, rollback and recovery evidence

P5 distinguishes three different claims:

1. **clean forward migration** — CI runs `php artisan migrate --force` on PostgreSQL;
2. **domain/schema rollback/reapply** — accepted phase/increment tests/evidence where implemented; and
3. **backup/restore recovery** — the CI container job demonstrates database backup/restore, release/image provenance and post-restore readiness.

A migration rollback test is not a database disaster-recovery proof. A database backup/restore is not proof that private object/media bytes are recoverable. P3 recovery-set boundaries remain authoritative.

## 12. Performance and capacity evidence

Use performance evidence only when the repository has an explicit bounded-work/query/capacity claim. P5 profiles identify the applicable `Performance` suite or other executable bounded-work evidence and link the owning operations/capability contract.

No domain receives an invented SLA, throughput target or query budget merely to fill this section.

## 13. Immutable acceptance identity

A new accepted phase/increment/DCP evidence record must identify, as applicable:

- repository/project identity;
- scope/phase/increment ID;
- exact validated implementation/content SHA;
- exact final evidence/status SHA when there is a second protected gate;
- protected workflow names and immutable GitHub Actions run IDs;
- conclusion for each required workflow;
- relevant job/test/static-analysis counts when available and useful;
- migration/staging/recovery/image-scan result where part of the gate;
- material performance/accessibility/security evidence references; and
- explicit deferred/non-capability boundaries.

A branch name, PR number, or statement that "CI passed" is supporting context, not sufficient immutable validation identity by itself.

## 14. Historical evidence hardening

P5 may **augment** an accepted historical exit record with recovered immutable GitHub identity when the acceptance decision is already established but the document omitted that identity.

Such an update must:

- preserve the original accepted scope and decision;
- label the new material as recovered/traceability hardening when appropriate;
- use actual GitHub commit/check evidence rather than inference;
- never change the historical implementation behavior; and
- never claim a check result that cannot be recovered.

## 15. Evidence retention and supersession

- Accepted phase/increment/DCP exit reports are retained as immutable historical evidence subject only to factual/navigation/traceability corrections.
- Living testing profiles are updated as current code/tests change.
- Historical test counts remain historical; they are not silently updated to current repository counts.
- A later acceptance record may supersede current behavior but does not delete the earlier historical record.
- Failed candidate runs that drove a correction may be retained in the owning exit/status record when they explain the acceptance path.
- Temporary diagnostic artifacts are not acceptance evidence unless explicitly retained by the accepted record.

## 16. P5 frozen inventory

`testing-evidence-coverage-matrix.md` is the authoritative P5 inventory. It must cover:

- all 14 canonical domains;
- all six canonical PHPUnit suites;
- backend/frontend protected quality gates;
- Dependency Review, CodeQL and CI evidence classes;
- migration, immutable image, staging, backup/restore and image-scan evidence;
- Phase 0–6 acceptance/accessibility records;
- Kingdoms K1–K3 domain-owned validation/accessibility/exit evidence;
- DCP P1–P4 acceptance evidence; and
- any historical accepted record that lacks sufficient immutable identity.

## 17. P5 CI enforcement

P5 CI should enforce high-signal documentation/traceability structure rather than parse arbitrary prose from every test:

- one current testing/evidence profile per canonical code domain;
- required profile metadata and 12-section order;
- profile links to canonical domain/security/operations/interfaces where applicable;
- exact canonical PHPUnit suite names/directories from `phpunit.xml` represented in the P5 matrix;
- backend/frontend quality command representation;
- protected workflow/evidence-class representation;
- historical Phase 0–6 and Kingdoms K1–K3 evidence indexes represented; and
- repository Markdown-link integrity.

## 18. P5 completion gate

P5 is Complete only when:

- 14/14 domain testing/evidence profiles are current;
- every critical domain invariant has discoverable validation coverage at the appropriate suite/evidence-class level;
- P1–P4 living contract/security/operations/interface claims are traceable to executable evidence classes;
- migration/rollback/recovery/performance/accessibility evidence is linked where applicable;
- accepted historical phase/increment evidence is clearly separated from current living contracts;
- accepted evidence records have sufficient immutable identity, with recoverable gaps hardened;
- P5 architecture/link checks pass; and
- exact P5 candidate and final evidence/status heads pass protected Dependency Review, CodeQL and complete CI.

P6 cannot begin before this gate closes.
